<?php
/**
 * Foundation functional smoke tests.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace WPFormVault\Tests\Functional;

use WPFormVault\Core\Plugin;

/**
 * Verifies the composition root remains request-idempotent in WordPress.
 */
final class PluginBootTest extends \WP_UnitTestCase {

	/**
	 * Repeated boot calls return one request-local root.
	 */
	public function test_repeated_boot_is_idempotent(): void {
		self::assertSame( Plugin::boot(), Plugin::boot() );
	}
}
