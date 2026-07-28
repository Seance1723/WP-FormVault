<?php
/**
 * Cryptographic randomness contract.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies deterministic test doubles and secure runtime bytes.
 */
interface RandomSourceInterface {

	/**
	 * Return the requested number of cryptographically secure bytes.
	 *
	 * @param int $length Number of bytes.
	 */
	public function bytes( int $length ): string;
}
