<?php
/**
 * Sanitized WordPress bootstrap diagnostics.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core;

use WPFormVault\Core\Contracts\DiagnosticSinkInterface;
use WPFormVault\Core\Value\GateResult;

defined( 'ABSPATH' ) || exit;

/**
 * Displays de-duplicated administrator notices for bootstrap failures.
 */
final class WordPressDiagnosticSink implements DiagnosticSinkInterface {

	/**
	 * @var array<string, GateResult>
	 */
	private array $failures = array();

	private bool $hook_registered = false;

	/**
	 * Record one safe failure and register the notice renderer once.
	 *
	 * @param GateResult $result Failed gate result.
	 */
	public function report( GateResult $result ): void {
		if ( $result->passed() ) {
			return;
		}

		$this->failures[ $result->code() ] = $result;

		if ( ! $this->hook_registered && function_exists( 'add_action' ) ) {
			add_action( 'admin_notices', array( $this, 'render' ), 10, 0 );
			$this->hook_registered = true;
		}
	}

	/**
	 * Render sanitized notices only to administrators who can manage plugins.
	 */
	public function render(): void {
		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		foreach ( $this->failures as $failure ) {
			$message = 'WP FormVault could not start: ' . $failure->message();
			$safe    = function_exists( 'esc_html' )
				? esc_html( $message )
				: htmlspecialchars( $message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );

			echo '<div class="notice notice-error"><p>' . $safe . '</p></div>';
		}
	}
}
