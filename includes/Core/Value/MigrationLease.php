<?php
/**
 * Immutable fenced migration lease.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Value;

use DateTimeImmutable;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Carries only the owner-token hash and current fencing token.
 */
final class MigrationLease {

	/**
	 * SHA-256 owner-token hash as lowercase hex.
	 *
	 * @var string
	 */
	private string $owner_hash;

	/**
	 * Monotonic fencing token.
	 *
	 * @var int
	 */
	private int $fencing_token;

	/**
	 * Lease expiry.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $expires_at;

	/**
	 * Store a validated lease.
	 *
	 * @param string            $owner_hash    SHA-256 owner-token hash.
	 * @param int               $fencing_token Monotonic fencing token.
	 * @param DateTimeImmutable $expires_at    UTC expiry instant.
	 * @throws InvalidArgumentException When the owner hash or fence is invalid.
	 */
	public function __construct(
		string $owner_hash,
		int $fencing_token,
		DateTimeImmutable $expires_at
	) {
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $owner_hash ) ) {
			throw new InvalidArgumentException( 'Migration lease owner hash must be lowercase SHA-256 hex.' );
		}

		if ( $fencing_token < 1 ) {
			throw new InvalidArgumentException( 'Migration lease fencing token must be positive.' );
		}

		$this->owner_hash    = $owner_hash;
		$this->fencing_token = $fencing_token;
		$this->expires_at    = $expires_at;
	}

	/**
	 * SHA-256 owner-token hash as lowercase hex.
	 */
	public function owner_hash(): string {
		return $this->owner_hash;
	}

	/**
	 * Current fencing token.
	 */
	public function fencing_token(): int {
		return $this->fencing_token;
	}

	/**
	 * Current lease expiry.
	 */
	public function expires_at(): DateTimeImmutable {
		return $this->expires_at;
	}
}
