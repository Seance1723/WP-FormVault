<?php
/**
 * UTC clock contract.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Contracts;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies deterministic UTC time to schema coordination.
 */
interface ClockInterface {

	/**
	 * Current immutable UTC instant.
	 */
	public function now(): DateTimeImmutable;
}
