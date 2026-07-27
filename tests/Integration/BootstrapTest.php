<?php
/**
 * WordPress integration bootstrap tests.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace WPFormVault\Tests\Integration;

use WPFormVault\Core\Plugin;

/**
 * Verifies the real WordPress test harness loads the guarded plugin.
 */
final class BootstrapTest extends \WP_UnitTestCase {

	/**
	 * The current foundation must reach the intentional pending-schema gate.
	 */
	public function test_plugin_reaches_pending_schema_gate(): void {
		self::assertSame( 'wp-formvault', WPFV_TEXT_DOMAIN );
		self::assertSame( Plugin::STATE_BLOCKED_SCHEMA, Plugin::boot()->state() );
	}

	/**
	 * The WordPress harness honors the requested single-site or multisite mode.
	 */
	public function test_requested_site_mode_is_active(): void {
		$multisite_requested = '1' === getenv( 'WPFV_TEST_MULTISITE' );

		self::assertSame( $multisite_requested, is_multisite() );
	}
}
