<?php
/**
 * Secure runtime random source.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Runtime;

use InvalidArgumentException;
use WPFormVault\Core\Contracts\RandomSourceInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Delegates to PHP's operating-system CSPRNG.
 */
final class SecureRandomSource implements RandomSourceInterface {

	/**
	 * Return the requested secure random bytes.
	 *
	 * @param int $length Number of bytes.
	 * @throws InvalidArgumentException When the requested length is not positive.
	 */
	public function bytes( int $length ): string {
		if ( $length < 1 ) {
			throw new InvalidArgumentException( 'Random byte length must be positive.' );
		}

		return random_bytes( $length );
	}
}
