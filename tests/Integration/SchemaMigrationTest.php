<?php
/**
 * WordPress/MySQL schema migration integration tests.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace WPFormVault\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use WPFormVault\Core\Database\ControlPlaneInstaller;
use WPFormVault\Core\Database\ControlPlaneSchema;
use WPFormVault\Core\Database\WordPressSchemaDatabase;
use WPFormVault\Core\Exception\SchemaException;
use WPFormVault\Core\Migrations\MigrationLeaseManager;
use WPFormVault\Core\Migrations\MigrationRegistry;
use WPFormVault\Core\Migrations\SchemaMigrationRunner;
use WPFormVault\Core\Migrations\SchemaStateStore;
use WPFormVault\Core\Runtime\SecureRandomSource;
use WPFormVault\Core\Runtime\SystemClock;
use WPFormVault\Core\Value\SchemaState;
use WPFormVault\Tests\Support\Integration\FrozenClock;
use WPFormVault\Tests\Support\Integration\SequenceRandomSource;

/**
 * Verifies the current-site control plane against a real WordPress database.
 */
final class SchemaMigrationTest extends \WP_UnitTestCase {

	/**
	 * Reviewed wpdb adapter.
	 *
	 * @var WordPressSchemaDatabase
	 */
	private WordPressSchemaDatabase $database;

	/**
	 * Current-site table definition.
	 *
	 * @var ControlPlaneSchema
	 */
	private ControlPlaneSchema $schema;

	/**
	 * Reset the shared control rows before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		global $wpdb;

		$this->database = new WordPressSchemaDatabase( $wpdb );
		$this->schema   = new ControlPlaneSchema( $wpdb->prefix, WPFV_TABLE_PREFIX );

		$installer = new ControlPlaneInstaller(
			$this->database,
			$this->schema,
			new SystemClock()
		);
		$installer->ensure();
		$this->reset_control_rows();
	}

	/**
	 * Leave the request-local plugin in a valid target-zero database state.
	 */
	public function tear_down(): void {
		$this->reset_control_rows();

		parent::tear_down();
	}

	/**
	 * The bootstrapped tables satisfy their exact column and index contract.
	 */
	public function test_control_plane_is_installed_for_current_site(): void {
		$installer = new ControlPlaneInstaller(
			$this->database,
			$this->schema,
			new SystemClock()
		);

		self::assertTrue( $installer->postconditions_met() );
		self::assertSame(
			array_keys( $this->schema->required_columns()[ $this->schema->schema_table() ] ),
			array_keys( $this->database->table_columns( $this->schema->schema_table() ) )
		);
		self::assertSame(
			array_keys( $this->schema->required_columns()[ $this->schema->locks_table() ] ),
			array_keys( $this->database->table_columns( $this->schema->locks_table() ) )
		);
	}

	/**
	 * A fresh singleton converges through bounded control-plane transitions.
	 */
	public function test_uninitialized_control_plane_converges_to_ready(): void {
		$this->set_state( 0, 0, SchemaState::UNINITIALIZED, 0, 0 );

		$result = $this->runner()->run();
		$row    = $this->state_row();
		$lock   = $this->lock_row();

		self::assertTrue( $result->passed() );
		self::assertSame( '0', $row['installed_version'] );
		self::assertSame( '0', $row['target_version'] );
		self::assertSame( SchemaState::READY, $row['state'] );
		self::assertSame( '3', $row['row_version'] );
		self::assertNotNull( $lock );
		self::assertSame( 'released', $lock['owner_context'] );
	}

	/**
	 * A ready target-zero check performs no writes or lease acquisitions.
	 */
	public function test_ready_schema_check_is_idempotent(): void {
		$before = $this->state_row();
		$result = $this->runner()->run();
		$after  = $this->state_row();

		self::assertTrue( $result->passed() );
		self::assertSame( $before, $after );
		self::assertNull( $this->lock_row() );
	}

	/**
	 * An active lease serializes acquisition and released fences remain monotonic.
	 */
	public function test_lease_serialization_and_monotonic_fencing(): void {
		$clock   = $this->clock();
		$random  = new SequenceRandomSource();
		$manager = new MigrationLeaseManager(
			$this->database,
			$this->schema,
			$clock,
			$random
		);

		$first = $manager->acquire( 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa' );

		self::assertNotNull( $first );
		self::assertNull( $manager->acquire( 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb' ) );
		self::assertTrue( $manager->active() );

		$manager->release( $first );

		self::assertFalse( $manager->active() );

		$second = $manager->acquire( 'cccccccc-cccc-4ccc-8ccc-cccccccccccc' );

		self::assertNotNull( $second );
		self::assertSame( $first->fencing_token() + 1, $second->fencing_token() );

		$lock = $this->lock_row();

		self::assertNotNull( $lock );
		self::assertSame( $second->owner_hash(), $lock['owner_hash'] );
		self::assertSame( 64, strlen( $lock['owner_hash'] ) );

		$manager->release( $second );
	}

	/**
	 * A retry after a failed state increments the persisted retry count.
	 */
	public function test_failed_run_increments_retry_count_before_state_change(): void {
		$this->set_state( 0, 0, SchemaState::FAILED, 2, 0 );

		$clock   = $this->clock();
		$random  = new SequenceRandomSource();
		$leases  = new MigrationLeaseManager(
			$this->database,
			$this->schema,
			$clock,
			$random
		);
		$lease   = $leases->acquire( 'dddddddd-dddd-4ddd-8ddd-dddddddddddd' );
		$store   = new SchemaStateStore( $this->database, $this->schema, $clock );
		$current = $store->read();

		self::assertNotNull( $lease );
		self::assertNotNull( $current );

		$store->mark_pending(
			$current,
			0,
			'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee',
			$lease
		);

		$row = $this->state_row();

		self::assertSame( SchemaState::PENDING, $row['state'] );
		self::assertSame( '3', $row['retry_count'] );

		$leases->release( $lease );
	}

	/**
	 * A previous owner cannot write after a newer fence has been acquired.
	 */
	public function test_stale_lease_cannot_mutate_schema_state(): void {
		$clock  = $this->clock();
		$random = new SequenceRandomSource();
		$leases = new MigrationLeaseManager(
			$this->database,
			$this->schema,
			$clock,
			$random
		);
		$store  = new SchemaStateStore( $this->database, $this->schema, $clock );
		$first  = $leases->acquire( '11111111-1111-4111-8111-111111111111' );
		$state  = $store->read();

		self::assertNotNull( $first );
		self::assertNotNull( $state );

		$leases->release( $first );

		$second = $leases->acquire( '22222222-2222-4222-8222-222222222222' );

		self::assertNotNull( $second );

		try {
			$store->mark_pending(
				$state,
				0,
				'33333333-3333-4333-8333-333333333333',
				$first
			);
			self::fail( 'The stale lease unexpectedly mutated schema state.' );
		} catch ( SchemaException $exception ) {
			self::assertSame( 'schema_state_transition_conflict', $exception->failure_code() );
		}

		self::assertSame( SchemaState::READY, $this->state_row()['state'] );

		$leases->release( $second );
	}

	/**
	 * Newer persisted versions block startup without a downgrade.
	 */
	public function test_newer_schema_is_blocked_without_downgrade(): void {
		$this->set_state( 1, 1, SchemaState::READY, 0, 0 );

		$result = $this->runner()->run();
		$row    = $this->state_row();

		self::assertFalse( $result->passed() );
		self::assertSame( 'schema_newer_than_code', $result->code() );
		self::assertSame( '1', $row['installed_version'] );
		self::assertSame( SchemaState::BLOCKED_NEWER, $row['state'] );
	}

	/**
	 * Build the target-zero production coordinator.
	 */
	private function runner(): SchemaMigrationRunner {
		$clock  = new SystemClock();
		$random = new SecureRandomSource();

		return new SchemaMigrationRunner(
			new ControlPlaneInstaller( $this->database, $this->schema, $clock ),
			new MigrationRegistry(),
			new SchemaStateStore( $this->database, $this->schema, $clock ),
			new MigrationLeaseManager(
				$this->database,
				$this->schema,
				$clock,
				$random
			),
			$random
		);
	}

	/**
	 * Build a stable UTC clock for lease assertions.
	 */
	private function clock(): FrozenClock {
		return new FrozenClock(
			new DateTimeImmutable( '2026-01-01 00:00:00', new DateTimeZone( 'UTC' ) )
		);
	}

	/**
	 * Read the complete test-relevant singleton state.
	 *
	 * @return array<string, mixed>
	 */
	private function state_row(): array {
		$table = $this->schema->schema_table();
		$row   = $this->database->fetch_row(
			"SELECT installed_version, target_version, state, retry_count, row_version
			FROM {$table}
			WHERE id = 1"
		);

		self::assertNotNull( $row );

		return $row;
	}

	/**
	 * Read the current lease identity without exposing raw owner material.
	 *
	 * @return array<string, mixed>|null
	 */
	private function lock_row(): ?array {
		$table = $this->schema->locks_table();

		return $this->database->fetch_row(
			"SELECT LOWER(HEX(owner_token_hash)) AS owner_hash, owner_context,
				fencing_token, expires_at
			FROM {$table}
			WHERE lock_key = 'schema_migration'"
		);
	}

	/**
	 * Restore exact state values for one scenario.
	 *
	 * @param int    $installed_version Installed version.
	 * @param int    $target_version    Target version.
	 * @param string $state             State name.
	 * @param int    $retry_count       Retry count.
	 * @param int    $row_version       Optimistic row version.
	 */
	private function set_state(
		int $installed_version,
		int $target_version,
		string $state,
		int $retry_count,
		int $row_version
	): void {
		$table = $this->schema->schema_table();
		$query = $this->database->prepare(
			"UPDATE {$table}
			SET installed_version = %d,
				target_version = %d,
				state = %s,
				current_migration = NULL,
				run_id = NULL,
				started_at = NULL,
				heartbeat_at = NULL,
				completed_at = NULL,
				failed_at = NULL,
				retry_count = %d,
				last_error_code = NULL,
				last_error_at = NULL,
				row_version = %d,
				updated_at = %s
			WHERE id = 1",
			array(
				$installed_version,
				$target_version,
				$state,
				$retry_count,
				$row_version,
				'2026-01-01 00:00:00',
			)
		);

		$this->database->execute( $query );
	}

	/**
	 * Remove any lease and restore a ready target-zero singleton.
	 */
	private function reset_control_rows(): void {
		$locks_table = $this->schema->locks_table();
		$delete      = $this->database->prepare(
			"DELETE FROM {$locks_table} WHERE lock_key = %s",
			array( MigrationLeaseManager::LOCK_KEY )
		);

		$this->database->execute( $delete );
		$this->set_state( 0, 0, SchemaState::READY, 0, 0 );
	}
}
