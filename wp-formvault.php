<?php
/**
 * Plugin Name:       WP FormVault
 * Description:       Centralized form-submission reporting, scheduling, workflow, export, and secure delivery for supported WordPress form plugins.
 * Version:           0.0.0-dev
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Text Domain:       wp-formvault
 * Domain Path:       /languages
 *
 * @package WPFormVault
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WPFV_VERSION' ) ) {
	define( 'WPFV_VERSION', '0.0.0-dev' );
}

if ( ! defined( 'WPFV_MINIMUM_WORDPRESS_VERSION' ) ) {
	define( 'WPFV_MINIMUM_WORDPRESS_VERSION', '6.5' );
}

if ( ! defined( 'WPFV_MINIMUM_PHP_VERSION' ) ) {
	define( 'WPFV_MINIMUM_PHP_VERSION', '8.1' );
}

if ( ! defined( 'WPFV_TEXT_DOMAIN' ) ) {
	define( 'WPFV_TEXT_DOMAIN', 'wp-formvault' );
}

if ( ! defined( 'WPFV_TABLE_PREFIX' ) ) {
	define( 'WPFV_TABLE_PREFIX', 'wpfv_' );
}

if ( ! defined( 'WPFV_PLUGIN_FILE' ) ) {
	define( 'WPFV_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'WPFV_PLUGIN_DIR' ) ) {
	define( 'WPFV_PLUGIN_DIR', __DIR__ . DIRECTORY_SEPARATOR );
}

if ( ! defined( 'WPFV_PLUGIN_BASENAME' ) ) {
	define( 'WPFV_PLUGIN_BASENAME', plugin_basename( WPFV_PLUGIN_FILE ) );
}

if ( ! defined( 'WPFV_PLUGIN_URL' ) ) {
	define( 'WPFV_PLUGIN_URL', plugin_dir_url( WPFV_PLUGIN_FILE ) );
}

require_once WPFV_PLUGIN_DIR . 'includes/Autoloader.php';

\WPFormVault\Autoloader::register();
