<?php
/**
 * PHPUnit bootstrap for WordPress-backed tests.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

$wpfv_test_root     = dirname( __DIR__, 3 );
$wpfv_wp_tests_dir  = getenv( 'WPFV_WP_TESTS_DIR' );
$wpfv_polyfills_dir = $wpfv_test_root . '/vendor/yoast/phpunit-polyfills';

if ( false === $wpfv_wp_tests_dir || '' === trim( $wpfv_wp_tests_dir ) ) {
	fwrite( STDERR, "WPFV_WP_TESTS_DIR must identify the isolated WordPress PHPUnit library.\n" );
	exit( 1 );
}

if ( ! is_file( $wpfv_wp_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "The WordPress PHPUnit functions bootstrap is missing.\n" );
	exit( 1 );
}

if ( ! is_dir( $wpfv_polyfills_dir ) ) {
	fwrite( STDERR, "The locked PHPUnit Polyfills dependency is missing.\n" );
	exit( 1 );
}

require_once $wpfv_test_root . '/vendor/autoload.php';

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $wpfv_polyfills_dir );

if ( '1' === getenv( 'WPFV_TEST_MULTISITE' ) ) {
	define( 'WP_TESTS_MULTISITE', true );
}

require_once $wpfv_wp_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () use ( $wpfv_test_root ): void {
		require $wpfv_test_root . '/wp-formvault.php';
	}
);

require $wpfv_wp_tests_dir . '/includes/bootstrap.php';
