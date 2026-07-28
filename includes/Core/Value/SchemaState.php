<?php
/**
 * Immutable per-site schema state.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Value;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Validates the schema singleton row before coordination decisions.
 */
final class SchemaState {

	public const UNINITIALIZED = 'uninitialized';

	public const PENDING = 'pending';

	public const RUNNING = 'running';

	public const AWAITING_BACKGROUND = 'awaiting_background';

	public const FAILED = 'failed';

	public const READY = 'ready';

	public const BLOCKED_NEWER = 'blocked_newer';

	/**
	 * Accepted migration states.
	 *
	 * @var string[]
	 */
	private const STATES = array(
		self::UNINITIALIZED,
		self::PENDING,
		self::RUNNING,
		self::AWAITING_BACKGROUND,
		self::FAILED,
		self::READY,
		self::BLOCKED_NEWER,
	);

	/**
	 * Last committed numbered migration.
	 *
	 * @var int
	 */
	private int $installed_version;

	/**
	 * Current code target recorded by the coordinator.
	 *
	 * @var int
	 */
	private int $target_version;

	/**
	 * Migration state.
	 *
	 * @var string
	 */
	private string $state;

	/**
	 * Current migration identifier, when any.
	 *
	 * @var string|null
	 */
	private ?string $current_migration;

	/**
	 * Optimistic state-row version.
	 *
	 * @var int
	 */
	private int $row_version;

	/**
	 * Store a validated state.
	 *
	 * @param int         $installed_version Last committed migration.
	 * @param int         $target_version    Current code target.
	 * @param string      $state             Migration state.
	 * @param string|null $current_migration Current migration identifier.
	 * @param int         $row_version       Optimistic row version.
	 */
	private function __construct(
		int $installed_version,
		int $target_version,
		string $state,
		?string $current_migration,
		int $row_version
	) {
		$this->installed_version = $installed_version;
		$this->target_version    = $target_version;
		$this->state             = $state;
		$this->current_migration = $current_migration;
		$this->row_version       = $row_version;
	}

	/**
	 * Hydrate a validated state from a database row.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @throws InvalidArgumentException When persisted state is malformed.
	 */
	public static function from_row( array $row ): self {
		$id                = self::unsigned_integer( $row['id'] ?? null );
		$installed_version = self::unsigned_integer( $row['installed_version'] ?? null );
		$target_version    = self::unsigned_integer( $row['target_version'] ?? null );
		$row_version       = self::unsigned_integer( $row['row_version'] ?? null );
		$state             = $row['state'] ?? null;
		$current_migration = $row['current_migration'] ?? null;

		if ( 1 !== $id ) {
			throw new InvalidArgumentException( 'Schema state must use singleton ID 1.' );
		}

		if ( ! is_string( $state ) || ! in_array( $state, self::STATES, true ) ) {
			throw new InvalidArgumentException( 'Schema state is invalid.' );
		}

		if ( null !== $current_migration ) {
			if (
				! is_string( $current_migration )
				|| 1 !== preg_match( '/^[a-z][a-z0-9_]{0,190}$/D', $current_migration )
			) {
				throw new InvalidArgumentException( 'Current migration identifier is invalid.' );
			}
		}

		return new self(
			$installed_version,
			$target_version,
			$state,
			$current_migration,
			$row_version
		);
	}

	/**
	 * Last committed numbered migration.
	 */
	public function installed_version(): int {
		return $this->installed_version;
	}

	/**
	 * Current code target recorded in the row.
	 */
	public function target_version(): int {
		return $this->target_version;
	}

	/**
	 * Current migration state.
	 */
	public function state(): string {
		return $this->state;
	}

	/**
	 * Current migration identifier.
	 */
	public function current_migration(): ?string {
		return $this->current_migration;
	}

	/**
	 * Optimistic state-row version.
	 */
	public function row_version(): int {
		return $this->row_version;
	}

	/**
	 * Parse one unsigned integer returned by wpdb.
	 *
	 * @param mixed $value Persisted scalar.
	 * @throws InvalidArgumentException When the value is not a non-negative integer.
	 */
	private static function unsigned_integer( mixed $value ): int {
		if (
			( ! is_int( $value ) && ! is_string( $value ) )
			|| 1 !== preg_match( '/^(?:0|[1-9][0-9]*)$/D', (string) $value )
		) {
			throw new InvalidArgumentException( 'Schema state contains an invalid unsigned integer.' );
		}

		$normalized = (string) $value;
		$maximum    = (string) PHP_INT_MAX;

		if (
			strlen( $normalized ) > strlen( $maximum )
			|| (
				strlen( $normalized ) === strlen( $maximum )
				&& strcmp( $normalized, $maximum ) > 0
			)
		) {
			throw new InvalidArgumentException( 'Schema state contains an integer outside the supported range.' );
		}

		return (int) $normalized;
	}
}
