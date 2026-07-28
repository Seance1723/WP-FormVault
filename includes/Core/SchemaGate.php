<?php
/**
 * Versioned per-site schema gate and lifecycle hook registrar.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core;

use Throwable;
use WPFormVault\Core\Contracts\DiagnosticSinkInterface;
use WPFormVault\Core\Contracts\GateInterface;
use WPFormVault\Core\Contracts\HookRegistrarInterface;
use WPFormVault\Core\Database\ControlPlaneInstaller;
use WPFormVault\Core\Database\ControlPlaneSchema;
use WPFormVault\Core\Database\WordPressSchemaDatabase;
use WPFormVault\Core\Migrations\MigrationLeaseManager;
use WPFormVault\Core\Migrations\MigrationRegistry;
use WPFormVault\Core\Migrations\SchemaMigrationRunner;
use WPFormVault\Core\Migrations\SchemaStateStore;
use WPFormVault\Core\Runtime\SecureRandomSource;
use WPFormVault\Core\Runtime\SystemClock;
use WPFormVault\Core\Value\GateResult;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the bounded coordinator before product services and on lifecycle checks.
 */
final class SchemaGate implements GateInterface, HookRegistrarInterface {

	/**
	 * Current-site runner, absent only when wpdb is unavailable.
	 *
	 * @var SchemaMigrationRunner|null
	 */
	private ?SchemaMigrationRunner $runner;

	/**
	 * Sanitized diagnostic sink.
	 *
	 * @var DiagnosticSinkInterface
	 */
	private DiagnosticSinkInterface $diagnostics;

	/**
	 * Whether lifecycle hooks were registered.
	 *
	 * @var bool
	 */
	private bool $hooks_registered = false;

	/**
	 * Configure an available or unavailable schema gate.
	 *
	 * @param SchemaMigrationRunner|null $runner      Current-site runner.
	 * @param DiagnosticSinkInterface    $diagnostics Sanitized failure reporter.
	 */
	public function __construct(
		?SchemaMigrationRunner $runner,
		DiagnosticSinkInterface $diagnostics
	) {
		$this->runner      = $runner;
		$this->diagnostics = $diagnostics;
	}

	/**
	 * Build the current site's production schema graph without performing I/O.
	 *
	 * @param DiagnosticSinkInterface $diagnostics Sanitized failure reporter.
	 */
	public static function from_runtime( DiagnosticSinkInterface $diagnostics ): self {
		try {
			$wpdb = $GLOBALS['wpdb'] ?? null;

			if ( ! $wpdb instanceof wpdb ) {
				return new self( null, $diagnostics );
			}

			$database  = new WordPressSchemaDatabase( $wpdb );
			$clock     = new SystemClock();
			$random    = new SecureRandomSource();
			$schema    = new ControlPlaneSchema( $database->table_prefix(), WPFV_TABLE_PREFIX );
			$installer = new ControlPlaneInstaller( $database, $schema, $clock );
			$registry  = new MigrationRegistry();
			$store     = new SchemaStateStore( $database, $schema, $clock );
			$leases    = new MigrationLeaseManager( $database, $schema, $clock, $random );
			$runner    = new SchemaMigrationRunner(
				$installer,
				$registry,
				$store,
				$leases,
				$random
			);

			return new self( $runner, $diagnostics );
		} catch ( Throwable ) {
			return new self( null, $diagnostics );
		}
	}

	/**
	 * Register activation and ordinary version checks once.
	 */
	public function register_hooks(): void {
		if ( $this->hooks_registered || null === $this->runner ) {
			return;
		}

		if ( function_exists( 'register_activation_hook' ) ) {
			register_activation_hook( WPFV_PLUGIN_FILE, array( $this, 'migrate' ) );
		}

		if ( function_exists( 'add_action' ) ) {
			add_action( 'plugins_loaded', array( $this, 'migrate' ), 1, 0 );
		}

		$this->hooks_registered = true;
	}

	/**
	 * Evaluate and, when required, converge the current site's bounded schema.
	 */
	public function evaluate(): GateResult {
		if ( null === $this->runner ) {
			return GateResult::failure(
				'schema_database_unavailable',
				'The WordPress database connection is unavailable for schema verification.'
			);
		}

		return $this->runner->run();
	}

	/**
	 * Lifecycle callback for activation and ordinary version checks.
	 */
	public function migrate(): void {
		$result = $this->evaluate();

		if ( ! $result->passed() ) {
			$this->diagnostics->report( $result );
		}
	}
}
