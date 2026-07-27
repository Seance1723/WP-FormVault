<?php
/**
 * WP FormVault composition root.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core;

use InvalidArgumentException;
use Throwable;
use WPFormVault\Core\Contracts\DiagnosticSinkInterface;
use WPFormVault\Core\Contracts\GateInterface;
use WPFormVault\Core\Contracts\HookRegistrarInterface;
use WPFormVault\Core\Value\GateResult;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates fail-closed bootstrap gates and product hook registration.
 */
final class Plugin {

	public const STATE_NEW = 'new';

	public const STATE_BLOCKED_DEPENDENCY = 'blocked_dependency';

	public const STATE_BLOCKED_COMPATIBILITY = 'blocked_compatibility';

	public const STATE_BLOCKED_SCHEMA = 'blocked_schema';

	public const STATE_BLOCKED_CONFIGURATION = 'blocked_configuration';

	public const STATE_BLOCKED_BOOTSTRAP = 'blocked_bootstrap';

	public const STATE_READY = 'ready';

	private static ?self $instance = null;

	private ServiceContainer $container;

	private GateResult $dependency_status;

	private GateInterface $compatibility_gate;

	private GateInterface $schema_gate;

	private DiagnosticSinkInterface $diagnostics;

	/**
	 * @var string[]
	 */
	private array $hook_registrar_ids;

	private string $state = self::STATE_NEW;

	/**
	 * @param ServiceContainer       $container            Request/site service graph.
	 * @param GateResult             $dependency_status    Packaged dependency result.
	 * @param GateInterface          $compatibility_gate   Runtime compatibility gate.
	 * @param GateInterface          $schema_gate          Per-site schema/migration gate.
	 * @param DiagnosticSinkInterface $diagnostics          Safe failure reporter.
	 * @param string[]               $hook_registrar_ids   Product hook registrar service IDs.
	 */
	public function __construct(
		ServiceContainer $container,
		GateResult $dependency_status,
		GateInterface $compatibility_gate,
		GateInterface $schema_gate,
		DiagnosticSinkInterface $diagnostics,
		array $hook_registrar_ids = array()
	) {
		foreach ( $hook_registrar_ids as $registrar_id ) {
			if ( ! is_string( $registrar_id ) || '' === trim( $registrar_id ) ) {
				throw new InvalidArgumentException( 'Hook registrar service IDs must be non-empty strings.' );
			}
		}

		if ( count( $hook_registrar_ids ) !== count( array_unique( $hook_registrar_ids ) ) ) {
			throw new InvalidArgumentException( 'Hook registrar service IDs must be unique.' );
		}

		$this->container          = $container;
		$this->dependency_status  = $dependency_status;
		$this->compatibility_gate = $compatibility_gate;
		$this->schema_gate        = $schema_gate;
		$this->diagnostics        = $diagnostics;
		$this->hook_registrar_ids = array_values( $hook_registrar_ids );
	}

	/**
	 * Build and start the production composition root once per request.
	 */
	public static function boot(): self {
		if ( null !== self::$instance ) {
			self::$instance->start();
			return self::$instance;
		}

		$site_id = function_exists( 'get_current_blog_id' )
			? (int) get_current_blog_id()
			: 1;

		if ( $site_id < 1 ) {
			$site_id = 1;
		}

		$container           = new ServiceContainer( $site_id );
		$diagnostics         = new WordPressDiagnosticSink();
		$compatibility_gate  = CompatibilityGate::from_runtime();
		$schema_gate         = new PendingSchemaGate();
		$dependency_status   = DependencyLoader::load( WPFV_PLUGIN_DIR );

		$container->set( DiagnosticSinkInterface::class, $diagnostics );
		$container->set( 'wpfv.core.site_id', $site_id );
		$container->set( 'wpfv.core.compatibility_gate', $compatibility_gate );
		$container->set( 'wpfv.core.schema_gate', $schema_gate );

		self::$instance = new self(
			$container,
			$dependency_status,
			$compatibility_gate,
			$schema_gate,
			$diagnostics
		);

		self::$instance->start();

		return self::$instance;
	}

	/**
	 * Run each gate and register product hooks at most once.
	 */
	public function start(): void {
		if ( self::STATE_NEW !== $this->state ) {
			return;
		}

		if ( ! $this->dependency_status->passed() ) {
			$this->block( self::STATE_BLOCKED_DEPENDENCY, $this->dependency_status );
			return;
		}

		$compatibility_status = $this->evaluate_gate(
			$this->compatibility_gate,
			'compatibility_gate_failed',
			'The runtime compatibility check could not be completed.'
		);

		if ( ! $compatibility_status->passed() ) {
			$this->block( self::STATE_BLOCKED_COMPATIBILITY, $compatibility_status );
			return;
		}

		$schema_status = $this->evaluate_gate(
			$this->schema_gate,
			'schema_gate_failed',
			'The database schema check could not be completed.'
		);

		if ( ! $schema_status->passed() ) {
			$this->block( self::STATE_BLOCKED_SCHEMA, $schema_status );
			return;
		}

		foreach ( $this->hook_registrar_ids as $registrar_id ) {
			if ( ! $this->container->has( $registrar_id ) ) {
				$this->block(
					self::STATE_BLOCKED_CONFIGURATION,
					GateResult::failure(
						'hook_registrar_missing',
						'A required product hook registrar is not configured.'
					)
				);
				return;
			}
		}

		try {
			$this->container->freeze();

			$registrars = array();

			foreach ( $this->hook_registrar_ids as $registrar_id ) {
				$registrar = $this->container->get( $registrar_id );

				if ( ! $registrar instanceof HookRegistrarInterface ) {
					throw new InvalidArgumentException( 'A hook registrar service has an invalid type.' );
				}

				$registrars[] = $registrar;
			}

			foreach ( $registrars as $registrar ) {
				$registrar->register_hooks();
			}
		} catch ( Throwable ) {
			$this->block(
				self::STATE_BLOCKED_BOOTSTRAP,
				GateResult::failure(
					'product_bootstrap_failed',
					'Product services could not be initialized safely.'
				)
			);
			return;
		}

		$this->state = self::STATE_READY;
	}

	/**
	 * Current bootstrap state.
	 */
	public function state(): string {
		return $this->state;
	}

	/**
	 * Evaluate a gate without exposing thrown details.
	 *
	 * @param GateInterface $gate             Gate to evaluate.
	 * @param string        $fallback_code    Stable fallback failure code.
	 * @param string        $fallback_message Sanitized fallback message.
	 */
	private function evaluate_gate(
		GateInterface $gate,
		string $fallback_code,
		string $fallback_message
	): GateResult {
		try {
			return $gate->evaluate();
		} catch ( Throwable ) {
			return GateResult::failure( $fallback_code, $fallback_message );
		}
	}

	/**
	 * Enter a terminal fail-closed state and report a safe diagnostic.
	 *
	 * @param string     $state  Terminal blocked state.
	 * @param GateResult $result Sanitized failure.
	 */
	private function block( string $state, GateResult $result ): void {
		$this->state = $state;

		try {
			$this->diagnostics->report( $result );
		} catch ( Throwable ) {
			// Diagnostics must never turn a controlled bootstrap stop into a fatal.
		}
	}
}
