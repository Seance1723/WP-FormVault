<?php
/**
 * Fail-closed schema placeholder.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core;

use WPFormVault\Core\Contracts\GateInterface;
use WPFormVault\Core\Value\GateResult;

defined( 'ABSPATH' ) || exit;

/**
 * Blocks product services until the migration runner exists.
 *
 * DB-002 replaces this gate with the versioned per-site schema gate.
 */
final class PendingSchemaGate implements GateInterface {

	/**
	 * Report that schema-dependent services cannot start yet.
	 */
	public function evaluate(): GateResult {
		return GateResult::failure(
			'schema_gate_pending',
			'Database installation and migration support is not implemented yet.'
		);
	}
}
