<?php
/**
 * Controlled schema-coordination exception.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Exception;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

defined( 'ABSPATH' ) || exit;

/**
 * Carries a stable redacted failure code without exposing database details.
 */
final class SchemaException extends RuntimeException {

	/**
	 * Stable machine-readable failure code.
	 *
	 * @var string
	 */
	private string $failure_code;

	/**
	 * Create a controlled schema failure.
	 *
	 * @param string         $failure_code Stable lowercase identifier.
	 * @param Throwable|null $previous     Internal cause retained only in memory.
	 * @throws InvalidArgumentException When the failure code is not safe and stable.
	 */
	public function __construct( string $failure_code, ?Throwable $previous = null ) {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{0,63}$/D', $failure_code ) ) {
			throw new InvalidArgumentException( 'Schema failure codes must be stable lowercase identifiers.' );
		}

		$this->failure_code = $failure_code;

		parent::__construct( 'A controlled schema operation failed.', 0, $previous );
	}

	/**
	 * Stable redacted failure code.
	 */
	public function failure_code(): string {
		return $this->failure_code;
	}
}
