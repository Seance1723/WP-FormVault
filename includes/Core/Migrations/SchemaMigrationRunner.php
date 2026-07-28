<?php
/**
 * Ordered per-site schema migration coordinator.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Migrations;

use Throwable;
use WPFormVault\Core\Contracts\RandomSourceInterface;
use WPFormVault\Core\Database\ControlPlaneInstaller;
use WPFormVault\Core\Exception\SchemaException;
use WPFormVault\Core\Value\GateResult;
use WPFormVault\Core\Value\MigrationLease;
use WPFormVault\Core\Value\SchemaState;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps controls, owns a fenced lease, and commits verified forward steps.
 */
final class SchemaMigrationRunner {

	public const CONTROL_PLANE_MIGRATION = 'control_plane_bootstrap';

	/**
	 * Control-plane installer/postcondition checker.
	 *
	 * @var ControlPlaneInstaller
	 */
	private ControlPlaneInstaller $installer;

	/**
	 * Contiguous registered migration chain.
	 *
	 * @var MigrationRegistry
	 */
	private MigrationRegistry $registry;

	/**
	 * Fenced schema-state store.
	 *
	 * @var SchemaStateStore
	 */
	private SchemaStateStore $state_store;

	/**
	 * Migration lease manager.
	 *
	 * @var MigrationLeaseManager
	 */
	private MigrationLeaseManager $leases;

	/**
	 * Cryptographic random source.
	 *
	 * @var RandomSourceInterface
	 */
	private RandomSourceInterface $random;

	/**
	 * Configure the current-site migration coordinator.
	 *
	 * @param ControlPlaneInstaller $installer   Control-plane installer.
	 * @param MigrationRegistry     $registry    Contiguous migration registry.
	 * @param SchemaStateStore      $state_store Fenced state store.
	 * @param MigrationLeaseManager $leases      Fenced lease manager.
	 * @param RandomSourceInterface $random      Cryptographic random source.
	 */
	public function __construct(
		ControlPlaneInstaller $installer,
		MigrationRegistry $registry,
		SchemaStateStore $state_store,
		MigrationLeaseManager $leases,
		RandomSourceInterface $random
	) {
		$this->installer   = $installer;
		$this->registry    = $registry;
		$this->state_store = $state_store;
		$this->leases      = $leases;
		$this->random      = $random;
	}

	/**
	 * Converge this site to the highest contiguous registered version.
	 */
	public function run(): GateResult {
		$target = $this->registry->target_version();

		try {
			$this->installer->ensure();
			$state        = $this->required_state();
			$active_lease = $this->leases->active();
		} catch ( SchemaException $exception ) {
			return $this->failure( $exception->failure_code() );
		} catch ( Throwable ) {
			return $this->failure( 'schema_control_plane_install_failed' );
		}

		if (
			$target === $state->installed_version()
			&& SchemaState::READY === $state->state()
			&& ! $active_lease
			&& $this->registry->postconditions_met_through( $target )
		) {
			return GateResult::pass();
		}

		if ( SchemaState::AWAITING_BACKGROUND === $state->state() ) {
			return $this->failure( 'schema_migration_background' );
		}

		try {
			$run_id = $this->uuid();
			$lease  = $this->leases->acquire( $run_id );
		} catch ( SchemaException $exception ) {
			return $this->failure( $exception->failure_code() );
		} catch ( Throwable ) {
			return $this->failure( 'schema_lease_acquire_failed' );
		}

		if ( null === $lease ) {
			return $this->failure( 'schema_migration_locked' );
		}

		$result = $this->run_owned( $lease, $run_id, $target );

		try {
			$this->leases->release( $lease );
		} catch ( Throwable ) {
			if ( $result->passed() ) {
				return $this->failure( 'schema_lease_release_failed' );
			}
		}

		return $result;
	}

	/**
	 * Run while holding one lease fence.
	 *
	 * @param MigrationLease $lease  Owned lease.
	 * @param string         $run_id Migration run UUID.
	 * @param int            $target Current code target.
	 */
	private function run_owned(
		MigrationLease $lease,
		string $run_id,
		int $target
	): GateResult {
		try {
			$state = $this->required_state();

			if ( $state->installed_version() > $target ) {
				$this->state_store->mark_blocked_newer( $state, $target, $lease );
				return $this->failure( 'schema_newer_than_code' );
			}

			if ( SchemaState::AWAITING_BACKGROUND === $state->state() ) {
				return $this->failure( 'schema_migration_background' );
			}

			$this->state_store->mark_pending( $state, $target, $run_id, $lease );
			$state = $this->required_state();

			if ( $state->installed_version() === $target ) {
				$this->state_store->mark_running(
					$state,
					self::CONTROL_PLANE_MIGRATION,
					$lease
				);
				$state = $this->required_state();

				if (
					! $this->installer->postconditions_met()
					|| ! $this->registry->postconditions_met_through( $target )
				) {
					return $this->record_failure(
						$state,
						$target,
						'migration_postcondition_failed',
						$lease
					);
				}
			} else {
				while ( $state->installed_version() < $target ) {
					$migration = $this->registry->migration_from( $state->installed_version() );

					if ( null === $migration ) {
						return $this->record_failure(
							$state,
							$target,
							'migration_registry_gap',
							$lease
						);
					}

					$this->state_store->mark_running( $state, $migration->id(), $lease );
					$state = $this->required_state();
					$lease = $this->leases->heartbeat( $lease );

					try {
						$migration->apply();
					} catch ( Throwable ) {
						return $this->record_failure(
							$state,
							$target,
							'migration_step_failed',
							$lease
						);
					}

					try {
						$postconditions_met = $migration->postconditions_met();
					} catch ( Throwable ) {
						$postconditions_met = false;
					}

					if ( ! $postconditions_met ) {
						return $this->record_failure(
							$state,
							$target,
							'migration_postcondition_failed',
							$lease
						);
					}

					$lease = $this->leases->heartbeat( $lease );
					$this->state_store->commit_step(
						$state,
						$migration->from_version(),
						$migration->to_version(),
						$target,
						$migration->id(),
						$lease
					);
					$state = $this->required_state();
				}
			}

			if ( ! $this->registry->postconditions_met_through( $target ) ) {
				return $this->record_failure(
					$state,
					$target,
					'migration_postcondition_failed',
					$lease
				);
			}

			$this->state_store->mark_ready( $state, $target, $lease );

			return GateResult::pass();
		} catch ( SchemaException $exception ) {
			return $this->record_current_failure(
				$target,
				$exception->failure_code(),
				$lease
			);
		} catch ( Throwable ) {
			return $this->record_current_failure(
				$target,
				'migration_runner_failed',
				$lease
			);
		}
	}

	/**
	 * Persist one controlled failure against the known optimistic state.
	 *
	 * @param SchemaState    $state        Current optimistic state.
	 * @param int            $target       Current code target.
	 * @param string         $failure_code Stable redacted failure code.
	 * @param MigrationLease $lease        Owned lease.
	 */
	private function record_failure(
		SchemaState $state,
		int $target,
		string $failure_code,
		MigrationLease $lease
	): GateResult {
		try {
			$this->state_store->mark_failed( $state, $target, $failure_code, $lease );
		} catch ( Throwable ) {
			return $this->failure( 'schema_failure_record_failed' );
		}

		return $this->failure( $failure_code );
	}

	/**
	 * Best-effort failure persistence after an unexpected controlled exception.
	 *
	 * @param int            $target       Current code target.
	 * @param string         $failure_code Stable redacted failure code.
	 * @param MigrationLease $lease        Owned lease.
	 */
	private function record_current_failure(
		int $target,
		string $failure_code,
		MigrationLease $lease
	): GateResult {
		try {
			$state = $this->required_state();
			return $this->record_failure( $state, $target, $failure_code, $lease );
		} catch ( Throwable ) {
			return $this->failure( $failure_code );
		}
	}

	/**
	 * Require the bootstrapped singleton row.
	 *
	 * @throws SchemaException When the singleton state row is missing.
	 */
	private function required_state(): SchemaState {
		$state = $this->state_store->read();

		if ( null === $state ) {
			throw new SchemaException( 'schema_state_missing' );
		}

		return $state;
	}

	/**
	 * Generate a UUIDv4 run identifier.
	 *
	 * @throws SchemaException When the random source violates its contract.
	 */
	private function uuid(): string {
		$bytes = $this->random->bytes( 16 );

		if ( 16 !== strlen( $bytes ) ) {
			throw new SchemaException( 'schema_random_source_invalid' );
		}

		$bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
		$bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
		$hex      = bin2hex( $bytes );

		return substr( $hex, 0, 8 )
			. '-'
			. substr( $hex, 8, 4 )
			. '-'
			. substr( $hex, 12, 4 )
			. '-'
			. substr( $hex, 16, 4 )
			. '-'
			. substr( $hex, 20, 12 );
	}

	/**
	 * Convert a stable schema failure code to a sanitized gate result.
	 *
	 * @param string $failure_code Stable redacted failure code.
	 */
	private function failure( string $failure_code ): GateResult {
		$messages = array(
			'schema_newer_than_code'              => 'The database schema is newer than this WP FormVault version.',
			'schema_migration_locked'             => 'Another WP FormVault database migration is currently running.',
			'schema_migration_background'         => 'WP FormVault is waiting for a background database migration to finish.',
			'migration_step_failed'               => 'A WP FormVault database migration step failed safely.',
			'migration_postcondition_failed'      => 'WP FormVault could not verify the migrated database schema.',
			'schema_control_plane_invalid'        => 'WP FormVault could not install or verify its migration control tables.',
			'schema_database_unavailable'         => 'The WordPress database connection is unavailable for schema verification.',
			'schema_state_invalid'                => 'WP FormVault found invalid database migration state.',
			'schema_state_missing'                => 'WP FormVault could not initialize its database migration state.',
			'schema_state_transition_conflict'    => 'WP FormVault stopped a conflicting database migration state change.',
			'schema_lease_lost'                   => 'WP FormVault stopped because database migration ownership changed.',
			'schema_failure_record_failed'        => 'WP FormVault stopped after a database migration state conflict.',
			'schema_lease_release_failed'         => 'WP FormVault could not safely release its database migration lease.',
			'schema_control_plane_install_failed' => 'WP FormVault could not install its migration control tables.',
		);
		$message  = $messages[ $failure_code ]
			?? 'WP FormVault could not complete the database schema check safely.';

		return GateResult::failure( $failure_code, $message );
	}
}
