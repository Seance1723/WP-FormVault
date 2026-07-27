<?php
/**
 * Standalone verification for the pre-Composer plugin foundation.
 *
 * Run from the repository root:
 * php tools/verify-foundation.php
 *
 * @package WPFormVault
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );

/**
 * Minimal WordPress function stub used by the standalone verifier.
 *
 * @param string $file Plugin file.
 * @return string
 */
function plugin_basename( string $file ): string {
	return basename( $file );
}

/**
 * Minimal WordPress function stub used by the standalone verifier.
 *
 * @param string $file Plugin file.
 * @return string
 */
function plugin_dir_url( string $file ): string {
	unset( $file );

	return 'https://example.test/wp-content/plugins/wp-formvault/';
}

require dirname( __DIR__ ) . '/wp-formvault.php';

$expected_constants = array(
	'WPFV_VERSION'                   => '0.0.0-dev',
	'WPFV_MINIMUM_WORDPRESS_VERSION' => '6.5',
	'WPFV_MINIMUM_PHP_VERSION'       => '8.1',
	'WPFV_TEXT_DOMAIN'               => 'wp-formvault',
	'WPFV_TABLE_PREFIX'              => 'wpfv_',
	'WPFV_PLUGIN_BASENAME'           => 'wp-formvault.php',
	'WPFV_PLUGIN_URL'                => 'https://example.test/wp-content/plugins/wp-formvault/',
);

foreach ( $expected_constants as $name => $expected_value ) {
	if ( ! defined( $name ) || constant( $name ) !== $expected_value ) {
		fwrite( STDERR, "Unexpected constant value: {$name}\n" );
		exit( 1 );
	}
}

if ( realpath( WPFV_PLUGIN_FILE ) !== realpath( dirname( __DIR__ ) . '/wp-formvault.php' ) ) {
	fwrite( STDERR, "WPFV_PLUGIN_FILE does not resolve to the bootstrap.\n" );
	exit( 1 );
}

if ( realpath( WPFV_PLUGIN_DIR ) !== realpath( dirname( __DIR__ ) ) ) {
	fwrite( STDERR, "WPFV_PLUGIN_DIR does not resolve to the repository root.\n" );
	exit( 1 );
}

if ( ! class_exists( 'WPFormVault\\Autoloader', false ) ) {
	fwrite( STDERR, "The WP FormVault autoloader class was not loaded.\n" );
	exit( 1 );
}

\WPFormVault\Autoloader::register();
\WPFormVault\Autoloader::autoload( 'UnrelatedVendor\\Example' );
\WPFormVault\Autoloader::autoload( 'WPFormVault\\..\\UnsafePath' );

$matching_loaders = 0;

foreach ( spl_autoload_functions() ?: array() as $loader ) {
	if (
		is_array( $loader )
		&& 'WPFormVault\\Autoloader' === $loader[0]
		&& 'autoload' === $loader[1]
	) {
		++$matching_loaders;
	}
}

if ( 1 !== $matching_loaders ) {
	fwrite( STDERR, "Expected one WP FormVault autoloader; found {$matching_loaders}.\n" );
	exit( 1 );
}

echo "WP FormVault foundation verification passed.\n";
