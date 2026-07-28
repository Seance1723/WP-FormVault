<?php
/**
 * Deterministic integration-test clock.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace WPFormVault\Tests\Support\Integration;

use DateTimeImmutable;
use WPFormVault\Core\Contracts\ClockInterface;

/**
 * Returns one fixed UTC instant.
 */
final class FrozenClock implements ClockInterface {

	/**
	 * Fixed instant.
	 *
	 * @var DateTimeImmutable
	 */
	private DateTimeImmutable $instant;

	/**
	 * Configure the fixed instant.
	 *
	 * @param DateTimeImmutable $instant Fixed UTC instant.
	 */
	public function __construct( DateTimeImmutable $instant ) {
		$this->instant = $instant;
	}

	/**
	 * Return the fixed instant.
	 */
	public function now(): DateTimeImmutable {
		return $this->instant;
	}
}
