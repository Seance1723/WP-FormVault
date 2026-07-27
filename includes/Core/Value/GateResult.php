<?php
/**
 * Immutable bootstrap gate result.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Value;

use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

/**
 * Carries a stable result code and a sanitized administrator message.
 */
final class GateResult {

	/**
	 * Whether the evaluated gate passed.
	 *
	 * @var bool
	 */
	private bool $passed;

	/**
	 * Stable machine-readable result code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Sanitized administrator-facing message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Store an immutable gate result.
	 *
	 * @param bool   $passed  Whether the gate passed.
	 * @param string $code    Stable machine-readable result code.
	 * @param string $message Sanitized administrator-facing message.
	 */
	private function __construct( bool $passed, string $code, string $message ) {
		$this->passed  = $passed;
		$this->code    = $code;
		$this->message = $message;
	}

	/**
	 * Create a passing result.
	 */
	public static function pass(): self {
		return new self( true, 'ok', '' );
	}

	/**
	 * Create a failed result.
	 *
	 * @param string $code    Stable lowercase result code.
	 * @param string $message Sanitized administrator-facing message.
	 * @throws InvalidArgumentException When the code or message is unsafe.
	 */
	public static function failure( string $code, string $message ): self {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]*$/D', $code ) ) {
			throw new InvalidArgumentException( 'Gate result codes must be stable lowercase identifiers.' );
		}

		if (
			'' === trim( $message )
			|| str_contains( $message, "\r" )
			|| str_contains( $message, "\n" )
		) {
			throw new InvalidArgumentException( 'Gate failure messages must be non-empty single-line text.' );
		}

		return new self( false, $code, $message );
	}

	/**
	 * Whether the gate passed.
	 */
	public function passed(): bool {
		return $this->passed;
	}

	/**
	 * Stable result code.
	 */
	public function code(): string {
		return $this->code;
	}

	/**
	 * Sanitized message.
	 */
	public function message(): string {
		return $this->message;
	}
}
