<?php
/**
 * Verify the FND-003 container and fail-closed composition root.
 *
 * Run from the repository root after generating locked dependencies:
 * php tools/verify-bootstrap.php
 *
 * @package WPFormVault
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ );

$GLOBALS['wp_version']        = '6.5';
$GLOBALS['wpfv_test_actions'] = array();

/**
 * WordPress plugin-basename stub.
 *
 * @param string $file Plugin file.
 */
function plugin_basename( string $file ): string {
	return basename( $file );
}

/**
 * WordPress plugin-directory URL stub.
 *
 * @param string $file Plugin file.
 */
function plugin_dir_url( string $file ): string {
	unset( $file );

	return 'https://example.test/wp-content/plugins/wp-formvault/';
}

/**
 * WordPress action-registration stub.
 *
 * @param string   $hook_name     Hook name.
 * @param mixed    $callback      Hook callback; WordPress accepts deferred callables.
 * @param int      $priority      Priority.
 * @param int      $accepted_args Accepted arguments.
 */
function add_action(
	string $hook_name,
	mixed $callback,
	int $priority = 10,
	int $accepted_args = 1
): bool {
	$GLOBALS['wpfv_test_actions'][ $hook_name ][] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);

	return true;
}

/**
 * WordPress completed-action stub.
 *
 * @param string $hook_name Hook name.
 */
function did_action( string $hook_name ): int {
	unset( $hook_name );

	return 0;
}

/**
 * WordPress active-action stub.
 *
 * @param string $hook_name Hook name.
 */
function doing_action( string $hook_name ): bool {
	unset( $hook_name );

	return false;
}

/**
 * Current-site stub.
 */
function get_current_blog_id(): int {
	return 7;
}

/**
 * Capability stub.
 *
 * @param string $capability Capability name.
 */
function current_user_can( string $capability ): bool {
	return 'activate_plugins' === $capability;
}

/**
 * HTML escaping stub.
 *
 * @param string $text Text to escape.
 */
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

/**
 * Fail one verifier assertion.
 *
 * @param string $message Failure message.
 * @return never
 */
function wpfv_bootstrap_fail( string $message ): never {
	fwrite( STDERR, "Bootstrap verification failed: {$message}\n" );
	exit( 1 );
}

/**
 * Assert verifier state.
 *
 * @param bool   $condition Required condition.
 * @param string $message   Failure message.
 */
function wpfv_bootstrap_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		wpfv_bootstrap_fail( $message );
	}
}

require dirname( __DIR__ ) . '/wp-formvault.php';

use WPFormVault\Core\Contracts\DiagnosticSinkInterface;
use WPFormVault\Core\Contracts\GateInterface;
use WPFormVault\Core\Contracts\HookRegistrarInterface;
use WPFormVault\Core\CompatibilityGate;
use WPFormVault\Core\Exception\ContainerException;
use WPFormVault\Core\Plugin;
use WPFormVault\Core\ServiceContainer;
use WPFormVault\Core\Value\GateResult;

/**
 * Local service contract for container type tests.
 */
interface WPFVTestServiceContract {
}

/**
 * Local service implementation.
 */
final class WPFVTestService implements WPFVTestServiceContract {
}

/**
 * Deterministic injected gate.
 */
final class WPFVTestGate implements GateInterface {

	public int $calls = 0;

	private GateResult $result;

	private bool $throw;

	public function __construct( GateResult $result, bool $throw = false ) {
		$this->result = $result;
		$this->throw  = $throw;
	}

	public function evaluate(): GateResult {
		++$this->calls;

		if ( $this->throw ) {
			throw new RuntimeException( 'sensitive internal gate detail' );
		}

		return $this->result;
	}
}

/**
 * Deterministic injected diagnostic sink.
 */
final class WPFVTestDiagnosticSink implements DiagnosticSinkInterface {

	/**
	 * @var GateResult[]
	 */
	public array $results = array();

	public function report( GateResult $result ): void {
		$this->results[] = $result;
	}
}

/**
 * Deterministic hook registrar.
 */
final class WPFVTestHookRegistrar implements HookRegistrarInterface {

	public int $registrations = 0;

	public function register_hooks(): void {
		++$this->registrations;
	}
}

/**
 * Require a controlled container exception.
 *
 * @param Closure $operation Operation expected to fail.
 * @param string  $message   Assertion failure message.
 */
function wpfv_expect_container_failure( Closure $operation, string $message ): void {
	try {
		$operation();
	} catch ( ContainerException ) {
		return;
	}

	wpfv_bootstrap_fail( $message );
}

$production_first  = Plugin::boot();
$production_second = Plugin::boot();

wpfv_bootstrap_assert( $production_first === $production_second, 'production boot must return one request-local root' );
wpfv_bootstrap_assert(
	Plugin::STATE_BLOCKED_SCHEMA === $production_first->state(),
	'production bootstrap must stop at the pending schema gate; got ' . $production_first->state()
);
wpfv_bootstrap_assert(
	isset( $GLOBALS['wpfv_test_actions']['plugins_loaded'] )
	&& 2 === count( $GLOBALS['wpfv_test_actions']['plugins_loaded'] ),
	'Action Scheduler must register exactly its reviewed early arbitration hooks'
);
wpfv_bootstrap_assert(
	! class_exists( 'ActionScheduler', false ),
	'WP FormVault must not initialize Action Scheduler before its WordPress lifecycle boundary'
);
wpfv_bootstrap_assert(
	isset( $GLOBALS['wpfv_test_actions']['admin_notices'] )
	&& 1 === count( $GLOBALS['wpfv_test_actions']['admin_notices'] ),
	'blocked production boot must register one administrator diagnostic hook'
);

ob_start();
call_user_func( $GLOBALS['wpfv_test_actions']['admin_notices'][0]['callback'] );
$diagnostic_output = (string) ob_get_clean();

wpfv_bootstrap_assert(
	str_contains( $diagnostic_output, 'Database installation and migration support is not implemented yet.' ),
	'blocked schema diagnostic must be actionable'
);
wpfv_bootstrap_assert(
	! str_contains( $diagnostic_output, dirname( __DIR__ ) ),
	'bootstrap diagnostics must not expose internal filesystem paths'
);

$missing_dependency = \WPFormVault\Core\DependencyLoader::load(
	dirname( __DIR__ ) . '/tools/fixtures/missing-runtime'
);
wpfv_bootstrap_assert(
	! $missing_dependency->passed() && 'dependency_tree_missing' === $missing_dependency->code(),
	'missing packaged dependencies must fail closed'
);

$compatible_runtime = new CompatibilityGate( '6.5', '8.1.0', 8 );
$old_wordpress      = new CompatibilityGate( '6.4.9', '8.1.0', 8 );
$old_php            = new CompatibilityGate( '6.5', '8.0.30', 8 );
$narrow_php         = new CompatibilityGate( '6.5', '8.1.0', 4 );

wpfv_bootstrap_assert( $compatible_runtime->evaluate()->passed(), 'minimum compatible runtime must pass' );
wpfv_bootstrap_assert(
	'wordpress_version_unsupported' === $old_wordpress->evaluate()->code(),
	'WordPress minimum must be enforced'
);
wpfv_bootstrap_assert( 'php_version_unsupported' === $old_php->evaluate()->code(), 'PHP minimum must be enforced' );
wpfv_bootstrap_assert(
	'php_architecture_unsupported' === $narrow_php->evaluate()->code(),
	'64-bit PHP must be enforced'
);

$shared_builds = 0;
$container     = new ServiceContainer( 7 );
$container->share(
	WPFVTestServiceContract::class,
	static function ( ServiceContainer $services ) use ( &$shared_builds ): WPFVTestServiceContract {
		++$shared_builds;
		wpfv_bootstrap_assert( 7 === $services->site_id(), 'factory must receive the current site graph' );
		return new WPFVTestService();
	}
);
$container->alias( 'wpfv.test.service_alias', WPFVTestServiceContract::class );

wpfv_bootstrap_assert( 0 === $shared_builds, 'shared services must be lazy' );

$shared_first  = $container->get( 'wpfv.test.service_alias' );
$shared_second = $container->get( WPFVTestServiceContract::class );

wpfv_bootstrap_assert( $shared_first === $shared_second, 'shared aliases must resolve one instance' );
wpfv_bootstrap_assert( 1 === $shared_builds, 'shared service factory must run once' );

$transient_builds = 0;
$container->transient(
	'wpfv.test.transient',
	static function () use ( &$transient_builds ): object {
		++$transient_builds;
		return new stdClass();
	}
);

$transient_first  = $container->get( 'wpfv.test.transient' );
$transient_second = $container->get( 'wpfv.test.transient' );

wpfv_bootstrap_assert( $transient_first !== $transient_second, 'transient factories must create distinct objects' );
wpfv_bootstrap_assert( 2 === $transient_builds, 'transient factory must run for every resolution' );

wpfv_expect_container_failure(
	static fn () => $container->set( WPFVTestServiceContract::class, new WPFVTestService() ),
	'duplicate service definitions must fail'
);
wpfv_expect_container_failure(
	static fn () => $container->get( 'wpfv.test.missing' ),
	'missing service resolution must fail'
);

$circular = new ServiceContainer( 7 );
$circular->share(
	'wpfv.test.circular_a',
	static fn ( ServiceContainer $services ): mixed => $services->get( 'wpfv.test.circular_b' )
);
$circular->share(
	'wpfv.test.circular_b',
	static fn ( ServiceContainer $services ): mixed => $services->get( 'wpfv.test.circular_a' )
);
wpfv_expect_container_failure(
	static fn () => $circular->get( 'wpfv.test.circular_a' ),
	'circular service factories must fail'
);

$alias_cycle = new ServiceContainer( 7 );
$alias_cycle->alias( 'wpfv.test.alias_a', 'wpfv.test.alias_b' );
$alias_cycle->alias( 'wpfv.test.alias_b', 'wpfv.test.alias_a' );
wpfv_expect_container_failure(
	static fn () => $alias_cycle->freeze(),
	'circular service aliases must fail before hook registration'
);

$wrong_type = new ServiceContainer( 7 );
$wrong_type->share(
	WPFVTestServiceContract::class,
	static fn (): object => new stdClass()
);
wpfv_expect_container_failure(
	static fn () => $wrong_type->get( WPFVTestServiceContract::class ),
	'factory results must satisfy class/interface service IDs'
);

$wrapped_failure = new ServiceContainer( 7 );
$wrapped_failure->share(
	'wpfv.test.factory_failure',
	static function (): never {
		throw new RuntimeException( 'sensitive factory detail' );
	}
);

try {
	$wrapped_failure->get( 'wpfv.test.factory_failure' );
	wpfv_bootstrap_fail( 'throwing service factories must fail' );
} catch ( ContainerException $exception ) {
	wpfv_bootstrap_assert(
		! str_contains( $exception->getMessage(), 'sensitive factory detail' ),
		'container messages must not expose factory exception details'
	);
}

$container->freeze();
$container->freeze();
wpfv_bootstrap_assert( $container->is_frozen(), 'container freeze must be idempotent' );
wpfv_expect_container_failure(
	static fn () => $container->set( 'wpfv.test.after_freeze', true ),
	'frozen containers must reject definition changes'
);

$site_a = new ServiceContainer( 11 );
$site_b = new ServiceContainer( 12 );
$site_a->share( 'wpfv.test.site_service', static fn (): object => new stdClass() );
$site_b->share( 'wpfv.test.site_service', static fn (): object => new stdClass() );
wpfv_bootstrap_assert( 11 === $site_a->site_id() && 12 === $site_b->site_id(), 'site IDs must remain graph-local' );
wpfv_bootstrap_assert(
	$site_a->get( 'wpfv.test.site_service' ) !== $site_b->get( 'wpfv.test.site_service' ),
	'service instances must not cross site graphs'
);

$registrar_builds = 0;
$ready_container  = new ServiceContainer( 7 );
$ready_container->share(
	'wpfv.test.registrar',
	static function () use ( &$registrar_builds ): HookRegistrarInterface {
		++$registrar_builds;
		return new WPFVTestHookRegistrar();
	}
);
$ready_compatibility = new WPFVTestGate( GateResult::pass() );
$ready_schema        = new WPFVTestGate( GateResult::pass() );
$ready_diagnostics   = new WPFVTestDiagnosticSink();
$ready_plugin        = new Plugin(
	$ready_container,
	GateResult::pass(),
	$ready_compatibility,
	$ready_schema,
	$ready_diagnostics,
	array( 'wpfv.test.registrar' )
);

wpfv_bootstrap_assert( 0 === $registrar_builds, 'product registrars must remain lazy before gate evaluation' );
$ready_plugin->start();
$ready_registrar = $ready_container->get( 'wpfv.test.registrar' );
$ready_plugin->start();

wpfv_bootstrap_assert( Plugin::STATE_READY === $ready_plugin->state(), 'passing gates must produce a ready root' );
wpfv_bootstrap_assert( $ready_container->is_frozen(), 'ready boot must freeze definitions before hooks' );
wpfv_bootstrap_assert( 1 === $registrar_builds, 'ready boot must construct a registrar once' );
wpfv_bootstrap_assert(
	$ready_registrar instanceof WPFVTestHookRegistrar && 1 === $ready_registrar->registrations,
	'hook registration must be idempotent'
);
wpfv_bootstrap_assert(
	1 === $ready_compatibility->calls && 1 === $ready_schema->calls,
	'each passing gate must run once'
);
wpfv_bootstrap_assert( array() === $ready_diagnostics->results, 'ready boot must not emit diagnostics' );

$blocked_builds     = 0;
$blocked_container  = new ServiceContainer( 7 );
$blocked_container->share(
	'wpfv.test.blocked_registrar',
	static function () use ( &$blocked_builds ): HookRegistrarInterface {
		++$blocked_builds;
		return new WPFVTestHookRegistrar();
	}
);
$blocked_compatibility = new WPFVTestGate( GateResult::pass() );
$blocked_schema        = new WPFVTestGate( GateResult::pass() );
$blocked_diagnostics   = new WPFVTestDiagnosticSink();
$blocked_plugin        = new Plugin(
	$blocked_container,
	GateResult::failure( 'dependency_test_failure', 'Packaged dependencies are unavailable.' ),
	$blocked_compatibility,
	$blocked_schema,
	$blocked_diagnostics,
	array( 'wpfv.test.blocked_registrar' )
);
$blocked_plugin->start();

wpfv_bootstrap_assert(
	Plugin::STATE_BLOCKED_DEPENDENCY === $blocked_plugin->state(),
	'dependency failure must be the first terminal gate'
);
wpfv_bootstrap_assert(
	0 === $blocked_compatibility->calls && 0 === $blocked_schema->calls && 0 === $blocked_builds,
	'dependency failure must prevent later gates and product construction'
);
wpfv_bootstrap_assert( 1 === count( $blocked_diagnostics->results ), 'dependency failure must report once' );
$blocked_plugin->start();
wpfv_bootstrap_assert( 1 === count( $blocked_diagnostics->results ), 'blocked boot must be idempotent' );

$compatibility_container = new ServiceContainer( 7 );
$compatibility_builds    = 0;
$compatibility_container->share(
	'wpfv.test.compatibility_registrar',
	static function () use ( &$compatibility_builds ): HookRegistrarInterface {
		++$compatibility_builds;
		return new WPFVTestHookRegistrar();
	}
);
$failed_compatibility = new WPFVTestGate(
	GateResult::failure( 'compatibility_test_failure', 'The runtime is unsupported.' )
);
$unreached_schema     = new WPFVTestGate( GateResult::pass() );
$compatibility_plugin = new Plugin(
	$compatibility_container,
	GateResult::pass(),
	$failed_compatibility,
	$unreached_schema,
	new WPFVTestDiagnosticSink(),
	array( 'wpfv.test.compatibility_registrar' )
);
$compatibility_plugin->start();

wpfv_bootstrap_assert(
	Plugin::STATE_BLOCKED_COMPATIBILITY === $compatibility_plugin->state(),
	'compatibility failure must stop boot'
);
wpfv_bootstrap_assert(
	1 === $failed_compatibility->calls && 0 === $unreached_schema->calls && 0 === $compatibility_builds,
	'compatibility failure must prevent schema and product construction'
);

$schema_container = new ServiceContainer( 7 );
$schema_builds    = 0;
$schema_container->share(
	'wpfv.test.schema_registrar',
	static function () use ( &$schema_builds ): HookRegistrarInterface {
		++$schema_builds;
		return new WPFVTestHookRegistrar();
	}
);
$passed_compatibility = new WPFVTestGate( GateResult::pass() );
$failed_schema        = new WPFVTestGate(
	GateResult::failure( 'schema_test_failure', 'The schema is unavailable.' )
);
$schema_plugin        = new Plugin(
	$schema_container,
	GateResult::pass(),
	$passed_compatibility,
	$failed_schema,
	new WPFVTestDiagnosticSink(),
	array( 'wpfv.test.schema_registrar' )
);
$schema_plugin->start();

wpfv_bootstrap_assert( Plugin::STATE_BLOCKED_SCHEMA === $schema_plugin->state(), 'schema failure must stop boot' );
wpfv_bootstrap_assert(
	1 === $passed_compatibility->calls && 1 === $failed_schema->calls && 0 === $schema_builds,
	'schema failure must prevent product construction'
);

$throwing_gate = new WPFVTestGate( GateResult::pass(), true );
$throw_plugin = new Plugin(
	new ServiceContainer( 7 ),
	GateResult::pass(),
	$throwing_gate,
	new WPFVTestGate( GateResult::pass() ),
	new WPFVTestDiagnosticSink()
);
$throw_plugin->start();
wpfv_bootstrap_assert(
	Plugin::STATE_BLOCKED_COMPATIBILITY === $throw_plugin->state(),
	'thrown gate details must become a controlled compatibility failure'
);

$missing_registrar_plugin = new Plugin(
	new ServiceContainer( 7 ),
	GateResult::pass(),
	new WPFVTestGate( GateResult::pass() ),
	new WPFVTestGate( GateResult::pass() ),
	new WPFVTestDiagnosticSink(),
	array( 'wpfv.test.unconfigured_registrar' )
);
$missing_registrar_plugin->start();
wpfv_bootstrap_assert(
	Plugin::STATE_BLOCKED_CONFIGURATION === $missing_registrar_plugin->state(),
	'missing hook registrars must fail before container freeze'
);

$invalid_registrar_container = new ServiceContainer( 7 );
$invalid_registrar_container->set( 'wpfv.test.invalid_registrar', new stdClass() );
$invalid_registrar_plugin = new Plugin(
	$invalid_registrar_container,
	GateResult::pass(),
	new WPFVTestGate( GateResult::pass() ),
	new WPFVTestGate( GateResult::pass() ),
	new WPFVTestDiagnosticSink(),
	array( 'wpfv.test.invalid_registrar' )
);
$invalid_registrar_plugin->start();
wpfv_bootstrap_assert(
	Plugin::STATE_BLOCKED_BOOTSTRAP === $invalid_registrar_plugin->state(),
	'invalid hook registrar types must fail safely'
);

echo "WP FormVault bootstrap verification passed: container, dependency loading, gates, diagnostics, hook idempotency, and site isolation valid.\n";
