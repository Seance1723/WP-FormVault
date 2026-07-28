<?php
/**
 * WordPress integration bootstrap tests.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace WPFormVault\Tests\Integration;

use WPFormVault\Core\Plugin;
use WPFormVault\Core\SchemaGate;

/**
 * Verifies the real WordPress test harness loads the guarded plugin.
 */
final class BootstrapTest extends \WP_UnitTestCase {

	/**
	 * The current foundation must reach ready after its bounded schema check.
	 */
	public function test_plugin_reaches_ready_state(): void {
		self::assertSame( 'wp-formvault', WPFV_TEXT_DOMAIN );
		self::assertSame( Plugin::STATE_READY, Plugin::boot()->state() );
	}

	/**
	 * The WordPress harness honors the requested single-site or multisite mode.
	 */
	public function test_requested_site_mode_is_active(): void {
		$multisite_requested = '1' === getenv( 'WPFV_TEST_MULTISITE' );

		self::assertSame( $multisite_requested, is_multisite() );
	}

	/**
	 * Activation and ordinary plugin loading both retain a schema check callback.
	 */
	public function test_schema_gate_registers_lifecycle_checks(): void {
		self::assertTrue(
			$this->has_schema_callback( 'activate_' . WPFV_PLUGIN_BASENAME )
		);
		self::assertTrue( $this->has_schema_callback( 'plugins_loaded' ) );
	}

	/**
	 * Whether one hook contains the production schema migration callback.
	 *
	 * @param string $hook_name WordPress hook name.
	 */
	private function has_schema_callback( string $hook_name ): bool {
		global $wp_filter;

		$hook = $wp_filter[ $hook_name ] ?? null;

		if ( ! $hook instanceof \WP_Hook ) {
			return false;
		}

		foreach ( $hook->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'] ?? null;

				if (
					is_array( $function )
					&& ( $function[0] ?? null ) instanceof SchemaGate
					&& 'migrate' === ( $function[1] ?? null )
				) {
					return true;
				}
			}
		}

		return false;
	}
}
