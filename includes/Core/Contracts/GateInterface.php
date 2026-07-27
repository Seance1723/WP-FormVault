<?php
/**
 * Bootstrap gate contract.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Contracts;

use WPFormVault\Core\Value\GateResult;

defined( 'ABSPATH' ) || exit;

/**
 * Evaluates one fail-closed bootstrap prerequisite.
 */
interface GateInterface {

	/**
	 * Evaluate the prerequisite without starting product services.
	 */
	public function evaluate(): GateResult;
}
