<?php
/**
 * Verify the installed QA toolchain and hosted CI lane contract.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

/**
 * Stop verification with one actionable message.
 *
 * @param string $message Failure message.
 */
function wpfv_qa_fail( string $message ): never {
	fwrite( STDERR, "QA tooling verification failed: {$message}\n" );
	exit( 1 );
}

/**
 * Require a condition to be true.
 *
 * @param bool   $condition Condition under test.
 * @param string $message   Failure message.
 */
function wpfv_qa_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		wpfv_qa_fail( $message );
	}
}

/**
 * Read and decode a JSON object.
 *
 * @param string $path JSON file path.
 * @return array<string, mixed>
 */
function wpfv_qa_read_json( string $path ): array {
	$contents = file_get_contents( $path );

	if ( false === $contents ) {
		wpfv_qa_fail( "cannot read {$path}" );
	}

	$decoded = json_decode( $contents, true );

	if ( ! is_array( $decoded ) ) {
		wpfv_qa_fail( "invalid JSON object in {$path}" );
	}

	return $decoded;
}

/**
 * Read a required text file.
 *
 * @param string $path Text file path.
 */
function wpfv_qa_read_text( string $path ): string {
	$contents = file_get_contents( $path );

	if ( false === $contents ) {
		wpfv_qa_fail( "cannot read {$path}" );
	}

	return $contents;
}

/**
 * Index Composer package versions by package name.
 *
 * @param array<string, mixed> $lock Composer lock object.
 * @return array<string, string>
 */
function wpfv_qa_package_versions( array $lock ): array {
	$versions = array();
	$groups   = array( 'packages', 'packages-dev' );

	foreach ( $groups as $group ) {
		$packages = $lock[ $group ] ?? array();

		if ( ! is_array( $packages ) ) {
			wpfv_qa_fail( "Composer lock {$group} must be an array" );
		}

		foreach ( $packages as $package ) {
			if (
				! is_array( $package )
				|| ! isset( $package['name'], $package['version'] )
				|| ! is_string( $package['name'] )
				|| ! is_string( $package['version'] )
			) {
				wpfv_qa_fail( "Composer lock {$group} contains an invalid package record" );
			}

			$versions[ $package['name'] ] = ltrim( $package['version'], 'v' );
		}
	}

	return $versions;
}

$root = dirname( __DIR__ );

$policy        = wpfv_qa_read_json( $root . '/docs/architecture/quality-policy.json' );
$composer      = wpfv_qa_read_json( $root . '/composer.json' );
$root_lock     = wpfv_qa_package_versions( wpfv_qa_read_json( $root . '/composer.lock' ) );
$compat_lock   = wpfv_qa_package_versions( wpfv_qa_read_json( $root . '/tools/phpcompatibility/composer.lock' ) );
$phpcs         = wpfv_qa_read_text( $root . '/phpcs.xml.dist' );
$compatibility = wpfv_qa_read_text( $root . '/phpcompatibility.xml.dist' );
$phpstan       = wpfv_qa_read_text( $root . '/phpstan.neon.dist' );
$phpunit       = wpfv_qa_read_text( $root . '/phpunit.xml.dist' );
$integration   = wpfv_qa_read_text( $root . '/phpunit.integration.xml.dist' );
$dockerfile    = wpfv_qa_read_text( $root . '/docker/dependency-build/Dockerfile' );
$runner        = wpfv_qa_read_text( $root . '/tools/run-wordpress-integration-tests.sh' );
$quality       = wpfv_qa_read_text( $root . '/.github/workflows/quality.yml' );
$forward       = wpfv_qa_read_text( $root . '/.github/workflows/forward-compatibility.yml' );
$performance   = wpfv_qa_read_text( $root . '/.github/workflows/release-candidate-performance.yml' );
$setup_action  = wpfv_qa_read_text( $root . '/.github/actions/setup-php-composer/action.yml' );

wpfv_qa_assert( 'policy_implemented' === ( $policy['status'] ?? null ), 'quality policy status must confirm implementation' );
wpfv_qa_assert( 9 === ( $policy['test_policy']['runner_major_for_all_php_lanes'] ?? null ), 'PHPUnit 9 policy drift' );
wpfv_qa_assert( 8 === ( $policy['static_analysis']['level'] ?? null ), 'PHPStan level drift' );

$expected_root_versions = array(
	'php-stubs/wordpress-stubs' => '6.5.7',
	'phpstan/phpstan'           => '2.2.6',
	'phpunit/phpunit'           => '9.6.35',
	'squizlabs/php_codesniffer' => '3.13.5',
	'wp-coding-standards/wpcs'  => '3.4.1',
	'yoast/phpunit-polyfills'   => '3.1.2',
);

foreach ( $expected_root_versions as $package => $version ) {
	wpfv_qa_assert(
		( $root_lock[ $package ] ?? null ) === $version,
		"locked {$package} must be {$version}"
	);
}

$expected_compatibility_versions = array(
	'phpcompatibility/php-compatibility'          => '10.0.0-alpha2',
	'phpcompatibility/phpcompatibility-paragonie' => '2.0.0-alpha2',
	'phpcompatibility/phpcompatibility-wp'        => '3.0.0-alpha2',
	'squizlabs/php_codesniffer'                   => '4.0.1',
);

foreach ( $expected_compatibility_versions as $package => $version ) {
	wpfv_qa_assert(
		( $compat_lock[ $package ] ?? null ) === $version,
		"isolated locked {$package} must be {$version}"
	);
}

$scripts = $composer['scripts'] ?? array();
wpfv_qa_assert( is_array( $scripts ), 'Composer scripts must be an object' );

foreach ( array( 'analyse', 'lint:phpcs', 'lint:phpcompatibility', 'test:unit', 'test:integration' ) as $script ) {
	wpfv_qa_assert( isset( $scripts[ $script ] ), "missing Composer script {$script}" );
}

foreach ( array( 'WordPress-Core', 'WordPress-Docs', 'WordPress-Extra' ) as $ruleset ) {
	wpfv_qa_assert( str_contains( $phpcs, "ref=\"{$ruleset}\"" ), "missing PHPCS ruleset {$ruleset}" );
}

wpfv_qa_assert( str_contains( $compatibility, 'ref="PHPCompatibilityWP"' ), 'missing PHPCompatibilityWP ruleset' );
wpfv_qa_assert( str_contains( $compatibility, 'name="testVersion" value="8.1-"' ), 'PHP compatibility floor drift' );
wpfv_qa_assert( str_contains( $phpstan, 'level: 8' ), 'PHPStan configuration is not level 8' );
wpfv_qa_assert( ! str_contains( $phpstan, 'baseline' ), 'PHPStan baselines are forbidden' );
wpfv_qa_assert( str_contains( $phpunit, 'tests/Support/Unit/bootstrap.php' ), 'unit bootstrap is not isolated' );
wpfv_qa_assert( str_contains( $integration, 'tests/Support/Integration/bootstrap.php' ), 'WordPress bootstrap is missing' );

foreach (
	array(
		'unit'                   => $phpunit,
		'integration'            => $integration,
		'functional'             => $integration,
		'security'               => $integration,
		'performance'            => $integration,
		'required-minimum-mysql' => $integration,
		'required-current'       => $integration,
		'required-multisite'     => $integration,
		'required-trunk'         => $integration,
	) as $suite => $configuration
) {
	wpfv_qa_assert( str_contains( $configuration, "testsuite name=\"{$suite}\"" ), "missing PHPUnit suite {$suite}" );
}

wpfv_qa_assert( str_contains( $dockerfile, 'curl' ), 'QA image must contain curl' );
wpfv_qa_assert( str_contains( $dockerfile, 'mysqli' ), 'QA image must contain mysqli' );
wpfv_qa_assert( str_contains( $runner, 'mktemp -d -t wpfv-wordpress-tests-' ), 'integration workspace must be isolated' );
wpfv_qa_assert( str_contains( $runner, 'Resolved WordPress runtime:' ), 'integration runner must log exact WordPress' );

$lane_workflows = array(
	'quality-minimum'                       => $quality,
	'quality-latest'                        => $quality,
	'integration-minimum-mysql'             => $quality,
	'integration-minimum-mariadb'           => $quality,
	'integration-current-php-band'          => $quality,
	'integration-current-mariadb-multisite' => $quality,
	'dependency-build-minimum'              => $quality,
	'wordpress-trunk-forward-compatibility' => $forward,
	'performance-release-candidate'         => $performance,
);

$policy_lanes = $policy['ci_policy']['required_lanes'] ?? array();
wpfv_qa_assert( is_array( $policy_lanes ), 'required CI lanes must be an array' );

$policy_lane_ids = array();
foreach ( $policy_lanes as $lane ) {
	if ( ! is_array( $lane ) || ! isset( $lane['id'] ) || ! is_string( $lane['id'] ) ) {
		wpfv_qa_fail( 'quality policy contains an invalid CI lane' );
	}

	$policy_lane_ids[] = $lane['id'];
}

sort( $policy_lane_ids );
$implemented_lane_ids = array_keys( $lane_workflows );
sort( $implemented_lane_ids );
wpfv_qa_assert( $policy_lane_ids === $implemented_lane_ids, 'implemented CI lane IDs differ from policy' );

foreach ( $lane_workflows as $lane_id => $workflow ) {
	$pattern = '/^  ' . preg_quote( $lane_id, '/' ) . ':\s*$/m';
	wpfv_qa_assert( 1 === preg_match( $pattern, $workflow ), "workflow job {$lane_id} is missing" );
}

wpfv_qa_assert( str_contains( $quality, 'pull_request:' ) && str_contains( $quality, 'push:' ), 'blocking workflow cadence drift' );
wpfv_qa_assert( 2 <= substr_count( $quality, 'php-version: latest' ), 'rolling blocking PHP lanes must resolve latest at run time' );
wpfv_qa_assert( str_contains( $forward, 'schedule:' ) && str_contains( $forward, 'continue-on-error: true' ), 'nightly lane policy drift' );
wpfv_qa_assert( str_contains( $forward, 'php-version: latest' ), 'nightly PHP lane must resolve latest at run time' );
wpfv_qa_assert( str_contains( $performance, 'prereleased' ), 'release-candidate trigger is missing' );
wpfv_qa_assert( str_contains( $performance, 'php-version: latest' ), 'performance PHP lane must resolve latest at run time' );
wpfv_qa_assert( str_contains( $setup_action, 'shivammathur/setup-php@v2' ), 'setup-php action version drift' );
wpfv_qa_assert( str_contains( $setup_action, 'actions/cache@v6' ), 'Composer cache action version drift' );
wpfv_qa_assert( str_contains( $quality, 'actions/checkout@v6' ), 'checkout action version drift' );

echo "WP FormVault QA tooling verification passed: locked analyzers, test harnesses, and nine CI lanes match policy.\n";
