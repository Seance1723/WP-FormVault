<?php
/**
 * Product hook registrar contract.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Registers one module's WordPress transport hooks.
 */
interface HookRegistrarInterface {

	/**
	 * Register hooks once after every bootstrap gate passes.
	 */
	public function register_hooks(): void;
}
