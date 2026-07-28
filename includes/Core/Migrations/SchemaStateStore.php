<?php
/**
 * Fenced schema-state persistence.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Migrations;

use InvalidArgumentException;
use WPFormVault\Core\Contracts\ClockInterface;
use WPFormVault\Core\Database\ControlPlaneSchema;
use WPFormVault\Core\Database\SchemaDatabaseInterface;
use WPFormVault\Core\Exception\SchemaException;
use WPFormVault\Core\Value\MigrationLease;
use WPFormVault\Core\Value\SchemaState;

defined( 'ABSPATH' ) || exit;

/**
 * Reads singleton state and commits transitions only for the current lease fence.
 */
final class SchemaStateStore {

	/**
	 * Reviewed database boundary.
	 *
	 * @var SchemaDatabaseInterface
	 */
	private SchemaDatabaseInterface $database;

	/**
	 * Current-site control table names.
	 *
	 * @var ControlPlaneSchema
	 */
	private ControlPlaneSchema $schema;

	/**
	 * UTC clock.
	 *
	 * @var ClockInterface
	 */
	private ClockInterface $clock;

	/**
	 * Configure current-site state persistence.
	 *
	 * @param SchemaDatabaseInterface $database Reviewed database boundary.
	 * @param ControlPlaneSchema      $schema   Current-site table names.
	 * @param ClockInterface          $clock    UTC clock.
	 */
	public function __construct(
		SchemaDatabaseInterface $database,
		ControlPlaneSchema $schema,
		ClockInterface $clock
	) {
		$this->database = $database;
		$this->schema   = $schema;
		$this->clock    = $clock;
	}

	/**
	 * Read and validate singleton state row 1.
	 *
	 * @throws SchemaException When persisted state is malformed.
	 */
	public function read(): ?SchemaState {
		$table = $this->schema->schema_table();
		$query = "SELECT id, installed_version, target_version, state, current_migration, row_version
			FROM {$table}
			WHERE id = 1";
		$row   = $this->database->fetch_row( $query );

		if ( null === $row ) {
			return null;
		}

		try {
			return SchemaState::from_row( $row );
		} catch ( InvalidArgumentException $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The previous exception is retained internally and is never rendered.
			throw new SchemaException( 'schema_state_invalid', $exception );
		}
	}

	/**
	 * Record that this run owns a pending migration target.
	 *
	 * @param SchemaState    $state  Current optimistic state.
	 * @param int            $target Current code target.
	 * @param string         $run_id Migration run UUID.
	 * @param MigrationLease $lease  Current fenced lease.
	 */
	public function mark_pending(
		SchemaState $state,
		int $target,
		string $run_id,
		MigrationLease $lease
	): void {
		$this->assert_target( $target );
		$this->assert_run_id( $run_id );

		$now = $this->timestamp();

		$this->fenced_update(
			'target_version = %d,
			retry_count = IF(state = %s, retry_count + 1, retry_count),
			state = %s,
			current_migration = NULL,
			run_id = %s,
			started_at = %s,
			heartbeat_at = %s,
			completed_at = NULL,
			failed_at = NULL,
			last_error_code = NULL,
			last_error_at = NULL,
			updated_at = %s',
			array(
				$target,
				SchemaState::FAILED,
				SchemaState::PENDING,
				$run_id,
				$now,
				$now,
				$now,
			),
			$state,
			$lease
		);
	}

	/**
	 * Enter a running step.
	 *
	 * @param SchemaState    $state        Current optimistic state.
	 * @param string         $migration_id Stable migration identifier.
	 * @param MigrationLease $lease        Current fenced lease.
	 */
	public function mark_running(
		SchemaState $state,
		string $migration_id,
		MigrationLease $lease
	): void {
		$this->assert_migration_id( $migration_id );
		$now = $this->timestamp();

		$this->fenced_update(
			'state = %s,
			current_migration = %s,
			heartbeat_at = %s,
			updated_at = %s',
			array(
				SchemaState::RUNNING,
				$migration_id,
				$now,
				$now,
			),
			$state,
			$lease
		);
	}

	/**
	 * Commit one verified version step.
	 *
	 * @param SchemaState    $state        Current optimistic state.
	 * @param int            $from         Required installed version.
	 * @param int            $to           Newly committed version.
	 * @param int            $target       Current code target.
	 * @param string         $migration_id Stable migration identifier.
	 * @param MigrationLease $lease        Current fenced lease.
	 * @throws SchemaException When versions, ownership, or optimistic state are invalid.
	 */
	public function commit_step(
		SchemaState $state,
		int $from,
		int $to,
		int $target,
		string $migration_id,
		MigrationLease $lease
	): void {
		if ( $from < 0 || $to !== $from + 1 ) {
			throw new SchemaException( 'schema_version_transition_invalid' );
		}

		$this->assert_target( $target );
		$this->assert_migration_id( $migration_id );
		$now = $this->timestamp();

		$this->fenced_update(
			'installed_version = %d,
			target_version = %d,
			state = %s,
			heartbeat_at = %s,
			updated_at = %s',
			array(
				$to,
				$target,
				SchemaState::RUNNING,
				$now,
				$now,
			),
			$state,
			$lease,
			'AND installed_version = %d AND state = %s AND current_migration = %s',
			array( $from, SchemaState::RUNNING, $migration_id )
		);
	}

	/**
	 * Commit a ready state only at the current target.
	 *
	 * @param SchemaState    $state  Current optimistic state.
	 * @param int            $target Current code target.
	 * @param MigrationLease $lease  Current fenced lease.
	 */
	public function mark_ready(
		SchemaState $state,
		int $target,
		MigrationLease $lease
	): void {
		$this->assert_target( $target );
		$now = $this->timestamp();

		$this->fenced_update(
			'target_version = %d,
			state = %s,
			current_migration = NULL,
			heartbeat_at = %s,
			completed_at = %s,
			failed_at = NULL,
			last_error_code = NULL,
			last_error_at = NULL,
			updated_at = %s',
			array(
				$target,
				SchemaState::READY,
				$now,
				$now,
				$now,
			),
			$state,
			$lease,
			'AND installed_version = %d',
			array( $target )
		);
	}

	/**
	 * Persist a redacted failure without advancing installed_version.
	 *
	 * @param SchemaState    $state        Current optimistic state.
	 * @param int            $target       Current code target.
	 * @param string         $failure_code Stable redacted error code.
	 * @param MigrationLease $lease        Current fenced lease.
	 */
	public function mark_failed(
		SchemaState $state,
		int $target,
		string $failure_code,
		MigrationLease $lease
	): void {
		$this->assert_target( $target );
		$this->assert_failure_code( $failure_code );
		$now = $this->timestamp();

		$this->fenced_update(
			'target_version = %d,
			state = %s,
			heartbeat_at = %s,
			failed_at = %s,
			last_error_code = %s,
			last_error_at = %s,
			updated_at = %s',
			array(
				$target,
				SchemaState::FAILED,
				$now,
				$now,
				$failure_code,
				$now,
				$now,
			),
			$state,
			$lease
		);
	}

	/**
	 * Persist a newer-than-code block without running a downgrade.
	 *
	 * @param SchemaState    $state  Current optimistic state.
	 * @param int            $target Current code target.
	 * @param MigrationLease $lease  Current fenced lease.
	 */
	public function mark_blocked_newer(
		SchemaState $state,
		int $target,
		MigrationLease $lease
	): void {
		$this->assert_target( $target );
		$now = $this->timestamp();

		$this->fenced_update(
			'target_version = %d,
			state = %s,
			current_migration = NULL,
			heartbeat_at = %s,
			completed_at = NULL,
			updated_at = %s',
			array(
				$target,
				SchemaState::BLOCKED_NEWER,
				$now,
				$now,
			),
			$state,
			$lease
		);
	}

	/**
	 * Execute an optimistic update guarded by the current lease hash and fence.
	 *
	 * @param string         $set_clause      Constant reviewed SET clause.
	 * @param array<mixed>   $set_arguments   SET placeholder arguments.
	 * @param SchemaState    $state           Current optimistic state.
	 * @param MigrationLease $lease           Current fenced lease.
	 * @param string         $extra_where     Constant additional predicates.
	 * @param array<mixed>   $extra_arguments Additional predicate arguments.
	 * @throws SchemaException When ownership/state changed concurrently.
	 */
	private function fenced_update(
		string $set_clause,
		array $set_arguments,
		SchemaState $state,
		MigrationLease $lease,
		string $extra_where = '',
		array $extra_arguments = array()
	): void {
		$schema_table = $this->schema->schema_table();
		$locks_table  = $this->schema->locks_table();
		$now          = $this->timestamp();
		$query        = $this->database->prepare(
			"UPDATE {$schema_table}
			SET {$set_clause}, row_version = row_version + 1
			WHERE id = 1
				AND row_version = %d
				AND EXISTS (
					SELECT 1
					FROM {$locks_table}
					WHERE lock_key = %s
						AND owner_token_hash = UNHEX(%s)
						AND fencing_token = %d
						AND expires_at > %s
				)
				{$extra_where}",
			array_merge(
				$set_arguments,
				array(
					$state->row_version(),
					MigrationLeaseManager::LOCK_KEY,
					$lease->owner_hash(),
					$lease->fencing_token(),
					$now,
				),
				$extra_arguments
			)
		);

		if ( 1 !== $this->database->execute( $query ) ) {
			throw new SchemaException( 'schema_state_transition_conflict' );
		}
	}

	/**
	 * Validate a non-negative target version.
	 *
	 * @param int $target Target schema version.
	 * @throws SchemaException When the target is negative.
	 */
	private function assert_target( int $target ): void {
		if ( $target < 0 ) {
			throw new SchemaException( 'schema_target_version_invalid' );
		}
	}

	/**
	 * Validate a migration identifier.
	 *
	 * @param string $migration_id Migration identifier.
	 * @throws SchemaException When the identifier is unsafe.
	 */
	private function assert_migration_id( string $migration_id ): void {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{0,190}$/D', $migration_id ) ) {
			throw new SchemaException( 'schema_migration_id_invalid' );
		}
	}

	/**
	 * Validate a stable redacted failure code.
	 *
	 * @param string $failure_code Failure code.
	 * @throws SchemaException When the failure code is unsafe.
	 */
	private function assert_failure_code( string $failure_code ): void {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{0,63}$/D', $failure_code ) ) {
			throw new SchemaException( 'schema_failure_code_invalid' );
		}
	}

	/**
	 * Validate a migration run UUID.
	 *
	 * @param string $run_id Migration run UUID.
	 * @throws SchemaException When the run ID is not a UUIDv4.
	 */
	private function assert_run_id( string $run_id ): void {
		if (
			1 !== preg_match(
				'/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',
				$run_id
			)
		) {
			throw new SchemaException( 'schema_run_id_invalid' );
		}
	}

	/**
	 * Current portable UTC DATETIME.
	 */
	private function timestamp(): string {
		return $this->clock->now()->format( 'Y-m-d H:i:s' );
	}
}
