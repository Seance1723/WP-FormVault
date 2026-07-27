<?php
/**
 * Packaged runtime dependency loader.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core;

use Throwable;
use WPFormVault\Core\Value\GateResult;

defined( 'ABSPATH' ) || exit;

/**
 * Loads isolated Composer packages and registers the bundled Action Scheduler.
 */
final class DependencyLoader {

	/**
	 * Required PHP extensions from the reviewed Composer runtime graph.
	 *
	 * @var string[]
	 */
	private const REQUIRED_EXTENSIONS = array(
		'ctype',
		'dom',
		'fileinfo',
		'filter',
		'gd',
		'iconv',
		'libxml',
		'mbstring',
		'simplexml',
		'xml',
		'xmlreader',
		'xmlwriter',
		'zip',
		'zlib',
	);

	/**
	 * Load the packaged dependencies without calling Action Scheduler APIs.
	 *
	 * @param string $plugin_directory Absolute plugin root directory.
	 */
	public static function load( string $plugin_directory ): GateResult {
		if ( '' === trim( $plugin_directory ) ) {
			return GateResult::failure(
				'dependency_path_invalid',
				'The packaged dependency directory could not be resolved.'
			);
		}

		if ( PHP_INT_SIZE !== 8 ) {
			return GateResult::failure(
				'dependency_platform_unsupported',
				'The packaged dependencies require a 64-bit PHP runtime.'
			);
		}

		$missing_extensions = array();

		foreach ( self::REQUIRED_EXTENSIONS as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				$missing_extensions[] = $extension;
			}
		}

		if ( array() !== $missing_extensions ) {
			return GateResult::failure(
				'dependency_extensions_missing',
				'Required PHP extensions are missing: ' . implode( ', ', $missing_extensions ) . '.'
			);
		}

		$plugin_directory = rtrim( $plugin_directory, '/\\' ) . DIRECTORY_SEPARATOR;
		$autoload_path    = $plugin_directory . 'vendor-prefixed' . DIRECTORY_SEPARATOR . 'autoload.php';

		if ( ! is_readable( $autoload_path ) ) {
			return GateResult::failure(
				'dependency_tree_missing',
				'The packaged runtime dependency tree is missing.'
			);
		}

		try {
			require_once $autoload_path;
		} catch ( Throwable ) {
			return GateResult::failure(
				'dependency_tree_invalid',
				'The packaged runtime dependency tree could not be loaded.'
			);
		}

		if ( ! class_exists( 'WPFormVault\\Vendor\\Composer\\Autoload\\ClassLoader', false ) ) {
			return GateResult::failure(
				'dependency_autoloader_invalid',
				'The isolated runtime autoloader is invalid.'
			);
		}

		if ( ! function_exists( 'add_action' ) ) {
			return GateResult::failure(
				'wordpress_hooks_unavailable',
				'WordPress hooks are unavailable during dependency registration.'
			);
		}

		$action_scheduler_path = $plugin_directory . 'libraries'
			. DIRECTORY_SEPARATOR
			. 'action-scheduler'
			. DIRECTORY_SEPARATOR
			. 'action-scheduler.php';

		if ( ! is_readable( $action_scheduler_path ) ) {
			return GateResult::failure(
				'action_scheduler_missing',
				'The packaged Action Scheduler library is missing.'
			);
		}

		try {
			require_once $action_scheduler_path;
		} catch ( Throwable ) {
			return GateResult::failure(
				'action_scheduler_invalid',
				'The packaged Action Scheduler library could not be registered.'
			);
		}

		if (
			! function_exists( 'action_scheduler_register_3_dot_9_dot_3' )
			&& ! class_exists( 'ActionScheduler', false )
			&& ! class_exists( 'ActionScheduler_Versions', false )
		) {
			return GateResult::failure(
				'action_scheduler_unregistered',
				'Action Scheduler did not register a compatible loader.'
			);
		}

		return GateResult::pass();
	}
}
