<?php
/**
 * Safe bootstrap diagnostic contract.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Contracts;

use WPFormVault\Core\Value\GateResult;

defined( 'ABSPATH' ) || exit;

/**
 * Receives sanitized bootstrap failures.
 */
interface DiagnosticSinkInterface {

	/**
	 * Report a sanitized failed gate result.
	 *
	 * @param GateResult $result Failed gate result.
	 */
	public function report( GateResult $result ): void;
}
