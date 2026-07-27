<?php
/**
 * Verify the accepted service-container and module-boundary architecture.
 *
 * Run from the repository root:
 * php tools/verify-architecture.php
 *
 * @package WPFormVault
 */

declare(strict_types=1);

$root       = dirname( __DIR__ );
$graph_path = $root . '/docs/architecture/module-boundaries.json';
$doc_path   = $root . '/docs/architecture/service-container-and-module-boundaries.md';
$plan_path  = $root . '/IMPLEMENTATION_PLAN.md';

/**
 * Stop with an actionable architecture verification failure.
 *
 * @param string $message Failure message.
 * @return never
 */
function wpfv_architecture_fail( string $message ): never {
	fwrite( STDERR, "Architecture verification failed: {$message}\n" );
	exit( 1 );
}

$graph_json = file_get_contents( $graph_path );

if ( false === $graph_json ) {
	wpfv_architecture_fail( "unable to read {$graph_path}" );
}

try {
	$graph = json_decode( $graph_json, true, 512, JSON_THROW_ON_ERROR );
} catch ( JsonException $exception ) {
	wpfv_architecture_fail( 'invalid module graph JSON: ' . $exception->getMessage() );
}

if ( ! is_array( $graph ) || 1 !== ( $graph['schema_version'] ?? null ) ) {
	wpfv_architecture_fail( 'module graph schema_version must be integer 1' );
}

if ( 'ARCH-004' !== ( $graph['owning_task'] ?? null ) ) {
	wpfv_architecture_fail( 'module graph must be owned by ARCH-004' );
}

$composition_root = $graph['composition_root'] ?? null;
$container        = $graph['container'] ?? null;
$public_surfaces  = $graph['public_cross_module_surfaces'] ?? null;
$terminal_modules = $graph['terminal_modules'] ?? null;
$unscoped_files   = $graph['unscoped_files'] ?? null;
$modules          = $graph['modules'] ?? null;

if (
	! is_array( $composition_root )
	|| 'WPFormVault\\Core\\Plugin' !== ( $composition_root['class'] ?? null )
	|| 'includes/Core/Plugin.php' !== ( $composition_root['path'] ?? null )
) {
	wpfv_architecture_fail( 'composition root contract is missing or changed' );
}

if (
	! is_array( $container )
	|| 'WPFormVault\\Core\\ServiceContainer' !== ( $container['class'] ?? null )
	|| 'includes/Core/ServiceContainer.php' !== ( $container['path'] ?? null )
) {
	wpfv_architecture_fail( 'service container contract is missing or changed' );
}

if ( ! is_array( $public_surfaces ) || array() === $public_surfaces ) {
	wpfv_architecture_fail( 'public cross-module surfaces must be a non-empty list' );
}

foreach ( $public_surfaces as $surface ) {
	if ( ! is_string( $surface ) || 1 !== preg_match( '/^[A-Z][A-Za-z0-9]*$/', $surface ) ) {
		wpfv_architecture_fail( 'invalid public cross-module namespace: ' . var_export( $surface, true ) );
	}
}

if ( count( $public_surfaces ) !== count( array_unique( $public_surfaces ) ) ) {
	wpfv_architecture_fail( 'public cross-module namespaces must be unique' );
}

if ( ! is_array( $terminal_modules ) || ! is_array( $unscoped_files ) || ! is_array( $modules ) ) {
	wpfv_architecture_fail( 'terminal_modules, unscoped_files, and modules must be arrays' );
}

if ( array() === $modules ) {
	wpfv_architecture_fail( 'module graph cannot be empty' );
}

if ( ! isset( $modules['Core'] ) || array() !== ( $modules['Core']['depends_on'] ?? null ) ) {
	wpfv_architecture_fail( 'Core must exist at the inward boundary with no module dependencies' );
}

$expected_module_directories = array();
$edge_count                  = 0;

foreach ( $modules as $module_name => $module ) {
	if ( ! is_string( $module_name ) || 1 !== preg_match( '/^[A-Z][A-Za-z0-9]*$/', $module_name ) ) {
		wpfv_architecture_fail( 'invalid module name: ' . var_export( $module_name, true ) );
	}

	if ( ! is_array( $module ) ) {
		wpfv_architecture_fail( "module {$module_name} definition must be an object" );
	}

	$module_path  = $module['path'] ?? null;
	$module_layer = $module['layer'] ?? null;
	$dependencies = $module['depends_on'] ?? null;
	$purpose      = $module['purpose'] ?? null;

	if ( "includes/{$module_name}" !== $module_path ) {
		wpfv_architecture_fail( "module {$module_name} path must be includes/{$module_name}" );
	}

	if ( ! is_int( $module_layer ) || $module_layer < 0 ) {
		wpfv_architecture_fail( "module {$module_name} layer must be a non-negative integer" );
	}

	if ( ! is_array( $dependencies ) || count( $dependencies ) !== count( array_unique( $dependencies ) ) ) {
		wpfv_architecture_fail( "module {$module_name} dependencies must be a unique list" );
	}

	if ( ! is_string( $purpose ) || '' === trim( $purpose ) ) {
		wpfv_architecture_fail( "module {$module_name} must document its purpose" );
	}

	if ( ! is_dir( $root . '/' . $module_path ) ) {
		wpfv_architecture_fail( "module directory is missing: {$module_path}" );
	}

	$expected_module_directories[] = $module_name;
	$edge_count                   += count( $dependencies );
}

foreach ( $modules as $module_name => $module ) {
	foreach ( $module['depends_on'] as $dependency ) {
		if ( ! is_string( $dependency ) || ! isset( $modules[ $dependency ] ) ) {
			wpfv_architecture_fail( "module {$module_name} references unknown dependency " . var_export( $dependency, true ) );
		}

		if ( $dependency === $module_name ) {
			wpfv_architecture_fail( "module {$module_name} cannot depend on itself" );
		}

		if ( $modules[ $dependency ]['layer'] >= $module['layer'] ) {
			wpfv_architecture_fail(
				"module {$module_name} (layer {$module['layer']}) must depend inward, but {$dependency} is layer {$modules[$dependency]['layer']}"
			);
		}
	}
}

foreach ( $terminal_modules as $terminal_module ) {
	if ( ! is_string( $terminal_module ) || ! isset( $modules[ $terminal_module ] ) ) {
		wpfv_architecture_fail( 'unknown terminal module: ' . var_export( $terminal_module, true ) );
	}

	foreach ( $modules as $module_name => $module ) {
		if ( in_array( $terminal_module, $module['depends_on'], true ) ) {
			wpfv_architecture_fail( "terminal module {$terminal_module} cannot be a dependency of {$module_name}" );
		}
	}
}

$actual_module_directories = array();
$includes_iterator         = new DirectoryIterator( $root . '/includes' );

foreach ( $includes_iterator as $entry ) {
	if ( $entry->isDot() || ! $entry->isDir() ) {
		continue;
	}

	$actual_module_directories[] = $entry->getFilename();
}

sort( $actual_module_directories );
sort( $expected_module_directories );

if ( $actual_module_directories !== $expected_module_directories ) {
	wpfv_architecture_fail(
		'includes module directories differ from the graph; expected ['
		. implode( ', ', $expected_module_directories )
		. '], found ['
		. implode( ', ', $actual_module_directories )
		. ']'
	);
}

$doc = file_get_contents( $doc_path );

if ( false === $doc ) {
	wpfv_architecture_fail( "unable to read {$doc_path}" );
}

$required_doc_contracts = array(
	'Composition root: `WPFormVault\Core\Plugin`',
	'Container: `WPFormVault\Core\ServiceContainer`',
	'No feature service may receive `ServiceContainer`',
	'The container is frozen before hook registration',
	'Admin` and `Rest` are terminal inbound modules',
	'Query contracts apply `AccessScope` inside the repository/query path',
	'FND-003` must additionally prove:',
);

foreach ( $required_doc_contracts as $required_contract ) {
	if ( ! str_contains( $doc, $required_contract ) ) {
		wpfv_architecture_fail( "architecture document is missing stable contract: {$required_contract}" );
	}
}

foreach ( array_keys( $modules ) as $module_name ) {
	if ( ! str_contains( $doc, "| `{$module_name}` |" ) ) {
		wpfv_architecture_fail( "architecture document dependency table is missing module {$module_name}" );
	}
}

$plan = file_get_contents( $plan_path );

if ( false === $plan ) {
	wpfv_architecture_fail( "unable to read {$plan_path}" );
}

$required_plan_contracts = array(
	'### 4.2 Service composition and module dependency contract **[HARDENED]**',
	'`WPFormVault\Core\Plugin` is the sole application composition root.',
	'The authoritative machine-readable graph is `docs/architecture/module-boundaries.json`',
);

foreach ( $required_plan_contracts as $required_contract ) {
	if ( ! str_contains( $plan, $required_contract ) ) {
		wpfv_architecture_fail( "implementation plan is missing stable contract: {$required_contract}" );
	}
}

$composition_root_path = str_replace( '/', DIRECTORY_SEPARATOR, $composition_root['path'] );
$container_path        = str_replace( '/', DIRECTORY_SEPARATOR, $container['path'] );
$unscoped_lookup       = array_fill_keys(
	array_map(
		static fn ( string $path ): string => str_replace( '/', DIRECTORY_SEPARATOR, $path ),
		$unscoped_files
	),
	true
);
$public_lookup         = array_fill_keys( $public_surfaces, true );
$php_iterator          = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root . '/includes', FilesystemIterator::SKIP_DOTS )
);
$checked_php_files     = 0;

foreach ( $php_iterator as $php_file ) {
	if ( ! $php_file->isFile() || 'php' !== strtolower( $php_file->getExtension() ) ) {
		continue;
	}

	++$checked_php_files;

	$absolute_path = $php_file->getPathname();
	$relative_path = substr( $absolute_path, strlen( $root ) + 1 );

	if ( isset( $unscoped_lookup[ $relative_path ] ) ) {
		continue;
	}

	$path_parts    = explode( DIRECTORY_SEPARATOR, $relative_path );
	$source_module = $path_parts[1] ?? null;

	if ( ! is_string( $source_module ) || ! isset( $modules[ $source_module ] ) ) {
		wpfv_architecture_fail( "PHP source is outside a declared module: {$relative_path}" );
	}

	$source = file_get_contents( $absolute_path );

	if ( false === $source ) {
		wpfv_architecture_fail( "unable to read PHP source: {$relative_path}" );
	}

	$is_composition_root = $composition_root_path === $relative_path;
	$is_container_class  = $container_path === $relative_path;

	if (
		! $is_composition_root
		&& ! $is_container_class
		&& str_contains( $source, $container['class'] )
	) {
		wpfv_architecture_fail( "container reference outside composition root: {$relative_path}" );
	}

	if ( $is_composition_root ) {
		continue;
	}

	preg_match_all(
		'/\bWPFormVault\\\\([A-Z][A-Za-z0-9]*)\\\\([A-Z][A-Za-z0-9]*)/',
		$source,
		$references,
		PREG_SET_ORDER
	);

	foreach ( $references as $reference ) {
		$target_module = $reference[1];
		$surface       = $reference[2];

		if ( ! isset( $modules[ $target_module ] ) || $target_module === $source_module ) {
			continue;
		}

		if ( ! in_array( $target_module, $modules[ $source_module ]['depends_on'], true ) ) {
			wpfv_architecture_fail(
				"forbidden module edge in {$relative_path}: {$source_module} -> {$target_module}"
			);
		}

		if ( ! isset( $public_lookup[ $surface ] ) ) {
			wpfv_architecture_fail(
				"private cross-module reference in {$relative_path}: {$target_module}\\{$surface}"
			);
		}
	}
}

echo sprintf(
	"WP FormVault architecture verification passed: %d modules, %d dependency edges, %d PHP files checked, acyclic inward layers and public boundaries valid.\n",
	count( $modules ),
	$edge_count,
	$checked_php_files
);
