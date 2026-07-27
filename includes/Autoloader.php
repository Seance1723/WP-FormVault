<?php
/**
 * Internal namespace autoloader.
 *
 * @package WPFormVault
 */

namespace WPFormVault;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves WP FormVault classes from the includes directory.
 *
 * This small loader keeps the foundation usable before Composer dependencies
 * are introduced. It is restricted to the WPFormVault namespace and validates
 * every namespace segment before constructing a path.
 */
final class Autoloader {

	private const NAMESPACE_PREFIX = 'WPFormVault\\';

	private static bool $registered = false;

	/**
	 * Register the class resolver once.
	 */
	public static function register(): void {
		if ( self::$registered ) {
			return;
		}

		spl_autoload_register( array( self::class, 'autoload' ) );
		self::$registered = true;
	}

	/**
	 * Load a class inside the plugin namespace.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function autoload( string $class ): void {
		$prefix_length = strlen( self::NAMESPACE_PREFIX );

		if ( 0 !== strncmp( $class, self::NAMESPACE_PREFIX, $prefix_length ) ) {
			return;
		}

		$relative_class = substr( $class, $prefix_length );

		if (
			'' === $relative_class
			|| 1 !== preg_match(
				'/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D',
				$relative_class
			)
		) {
			return;
		}

		$relative_path = str_replace( '\\', DIRECTORY_SEPARATOR, $relative_class );
		$class_file    = WPFV_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $relative_path . '.php';

		if ( is_readable( $class_file ) ) {
			require_once $class_file;
		}
	}
}
