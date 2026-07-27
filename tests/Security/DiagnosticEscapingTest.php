<?php
/**
 * Bootstrap diagnostic security regression tests.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace WPFormVault\Tests\Security;

use WPFormVault\Core\Value\GateResult;
use WPFormVault\Core\WordPressDiagnosticSink;

/**
 * Verifies administrator diagnostics encode hostile markup.
 */
final class DiagnosticEscapingTest extends \WP_UnitTestCase {

	/**
	 * A failure message cannot inject markup into the admin notice.
	 */
	public function test_diagnostic_message_is_html_encoded(): void {
		$administrator_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
		wp_set_current_user( $administrator_id );

		$sink = new WordPressDiagnosticSink();
		$sink->report( GateResult::failure( 'test_escaping', '<script>alert(1)</script>' ) );

		ob_start();
		$sink->render();
		$output = (string) ob_get_clean();

		self::assertStringNotContainsString( '<script>', $output );
		self::assertStringContainsString( '&lt;script&gt;', $output );
	}
}
