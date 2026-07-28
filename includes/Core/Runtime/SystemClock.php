<?php
/**
 * System UTC clock.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Runtime;

use DateTimeImmutable;
use DateTimeZone;
use WPFormVault\Core\Contracts\ClockInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies immutable UTC timestamps.
 */
final class SystemClock implements ClockInterface {

	/**
	 * Current immutable UTC instant.
	 */
	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}
}
