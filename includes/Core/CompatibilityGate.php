<?php
/**
 * Runtime platform compatibility gate.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core;

use WPFormVault\Core\Contracts\GateInterface;
use WPFormVault\Core\Value\GateResult;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies the runtime against the advertised minimum platform.
 */
final class CompatibilityGate implements GateInterface {

	/**
	 * WordPress version under evaluation.
	 *
	 * @var string
	 */
	private string $wordpress_version;

	/**
	 * PHP version under evaluation.
	 *
	 * @var string
	 */
	private string $php_version;

	/**
	 * PHP integer size in bytes.
	 *
	 * @var int
	 */
	private int $php_integer_size;

	/**
	 * Store the runtime values to evaluate.
	 *
	 * @param string $wordpress_version Current WordPress version.
	 * @param string $php_version       Current PHP version.
	 * @param int    $php_integer_size  PHP integer size in bytes.
	 */
	public function __construct( string $wordpress_version, string $php_version, int $php_integer_size ) {
		$this->wordpress_version = $wordpress_version;
		$this->php_version       = $php_version;
		$this->php_integer_size  = $php_integer_size;
	}

	/**
	 * Build the gate from the current WordPress/PHP runtime.
	 */
	public static function from_runtime(): self {
		$wordpress_version = $GLOBALS['wp_version'] ?? '0';

		return new self( $wordpress_version, PHP_VERSION, PHP_INT_SIZE );
	}

	/**
	 * Verify PHP, architecture, and WordPress minimums.
	 */
	public function evaluate(): GateResult {
		if ( version_compare( $this->php_version, WPFV_MINIMUM_PHP_VERSION, '<' ) ) {
			return GateResult::failure(
				'php_version_unsupported',
				'PHP ' . WPFV_MINIMUM_PHP_VERSION . ' or newer is required.'
			);
		}

		if ( 8 !== $this->php_integer_size ) {
			return GateResult::failure(
				'php_architecture_unsupported',
				'A 64-bit PHP runtime is required.'
			);
		}

		if ( version_compare( $this->wordpress_version, WPFV_MINIMUM_WORDPRESS_VERSION, '<' ) ) {
			return GateResult::failure(
				'wordpress_version_unsupported',
				'WordPress ' . WPFV_MINIMUM_WORDPRESS_VERSION . ' or newer is required.'
			);
		}

		return GateResult::pass();
	}
}
