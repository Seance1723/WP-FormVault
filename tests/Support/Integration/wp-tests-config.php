<?php
/**
 * Environment-driven WordPress integration-test configuration.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

$wpfv_test_core_dir = getenv( 'WPFV_WP_CORE_DIR' );

if ( false === $wpfv_test_core_dir || '' === trim( $wpfv_test_core_dir ) ) {
	throw new RuntimeException( 'WPFV_WP_CORE_DIR must identify the isolated WordPress test installation.' );
}

define( 'ABSPATH', rtrim( $wpfv_test_core_dir, '/\\' ) . '/' );
define( 'DB_NAME', getenv( 'WPFV_TEST_DB_NAME' ) ? getenv( 'WPFV_TEST_DB_NAME' ) : 'wordpress_test' );
define( 'DB_USER', getenv( 'WPFV_TEST_DB_USER' ) ? getenv( 'WPFV_TEST_DB_USER' ) : 'root' );
define( 'DB_PASSWORD', getenv( 'WPFV_TEST_DB_PASSWORD' ) ? getenv( 'WPFV_TEST_DB_PASSWORD' ) : '' );
define( 'DB_HOST', getenv( 'WPFV_TEST_DB_HOST' ) ? getenv( 'WPFV_TEST_DB_HOST' ) : '127.0.0.1' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'WP_DEBUG', true );
define( 'WP_TESTS_DOMAIN', 'example.test' );
define( 'WP_TESTS_EMAIL', 'admin@example.test' );
define( 'WP_TESTS_TITLE', 'WP FormVault Tests' );
define( 'WP_PHP_BINARY', 'php' );

$table_prefix = 'wptests_';
