<?php
/**
 * Ordered migration registry.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Migrations;

use InvalidArgumentException;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Validates one contiguous forward-only migration chain.
 */
final class MigrationRegistry {

	/**
	 * Migrations keyed by required installed version.
	 *
	 * @var array<int, MigrationInterface>
	 */
	private array $migrations = array();

	/**
	 * Highest contiguous registered migration.
	 *
	 * @var int
	 */
	private int $target_version = 0;

	/**
	 * Validate and freeze the registered chain.
	 *
	 * @param array<array-key, mixed> $migrations Candidate migration objects.
	 * @throws InvalidArgumentException When IDs or versions are invalid/non-contiguous.
	 */
	public function __construct( array $migrations = array() ) {
		$by_from = array();
		$ids     = array();

		foreach ( $migrations as $migration ) {
			if ( ! $migration instanceof MigrationInterface ) {
				throw new InvalidArgumentException( 'Every registered migration must implement MigrationInterface.' );
			}

			$id   = $migration->id();
			$from = $migration->from_version();
			$to   = $migration->to_version();

			if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{0,190}$/D', $id ) ) {
				throw new InvalidArgumentException( 'Migration IDs must be stable lowercase identifiers.' );
			}

			if ( isset( $ids[ $id ] ) ) {
				throw new InvalidArgumentException( 'Migration IDs must be unique.' );
			}

			if ( $from < 0 || $to !== $from + 1 ) {
				throw new InvalidArgumentException( 'Every migration must advance exactly one version.' );
			}

			if ( isset( $by_from[ $from ] ) ) {
				throw new InvalidArgumentException( 'Migration from-versions must be unique.' );
			}

			$ids[ $id ]       = true;
			$by_from[ $from ] = $migration;
		}

		ksort( $by_from );

		$expected_from = 0;

		foreach ( $by_from as $from => $migration ) {
			if ( $expected_from !== $from ) {
				throw new InvalidArgumentException( 'The migration chain must be contiguous from version zero.' );
			}

			$this->migrations[ $from ] = $migration;
			$expected_from             = $migration->to_version();
		}

		$this->target_version = $expected_from;
	}

	/**
	 * Highest contiguous registered migration.
	 */
	public function target_version(): int {
		return $this->target_version;
	}

	/**
	 * Migration starting at an installed version.
	 *
	 * @param int $installed_version Installed version to resolve.
	 */
	public function migration_from( int $installed_version ): ?MigrationInterface {
		return $this->migrations[ $installed_version ] ?? null;
	}

	/**
	 * Whether every committed step through a version still satisfies postconditions.
	 *
	 * @param int $installed_version Last committed schema version.
	 */
	public function postconditions_met_through( int $installed_version ): bool {
		if ( $installed_version < 0 || $installed_version > $this->target_version ) {
			return false;
		}

		foreach ( $this->migrations as $migration ) {
			if ( $migration->to_version() > $installed_version ) {
				break;
			}

			try {
				if ( ! $migration->postconditions_met() ) {
					return false;
				}
			} catch ( Throwable ) {
				return false;
			}
		}

		return true;
	}
}
