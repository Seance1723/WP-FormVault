<?php
/**
 * Deterministic integration-test random source.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace WPFormVault\Tests\Support\Integration;

use WPFormVault\Core\Contracts\RandomSourceInterface;

/**
 * Returns a distinct repeated byte for each request.
 */
final class SequenceRandomSource implements RandomSourceInterface {

	/**
	 * Next byte value.
	 *
	 * @var int
	 */
	private int $next = 1;

	/**
	 * Return a deterministic byte sequence of the requested length.
	 *
	 * @param int $length Requested byte count.
	 */
	public function bytes( int $length ): string {
		$value      = str_repeat( chr( $this->next ), $length );
		$this->next = ( $this->next % 255 ) + 1;

		return $value;
	}
}
