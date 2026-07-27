<?php
/**
 * Performance-harness smoke test.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace WPFormVault\Tests\Performance;

use WPFormVault\Core\ServiceContainer;

/**
 * Proves the release-candidate performance suite is executable.
 *
 * Real scale thresholds and 1k/10k/100k data fixtures remain QA-006.
 */
final class ServiceContainerHarnessTest extends \WP_UnitTestCase {

	/**
	 * Shared-service resolution stays stable over repeated access.
	 */
	public function test_shared_service_resolution_harness(): void {
		$container = new ServiceContainer( 1 );
		$service   = new \stdClass();

		$container->set( 'test.service', $service );

		for ( $iteration = 0; $iteration < 1000; ++$iteration ) {
			self::assertSame( $service, $container->get( 'test.service' ) );
		}
	}
}
