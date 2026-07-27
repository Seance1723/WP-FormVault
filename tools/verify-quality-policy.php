<?php
/**
 * Verify the accepted engineering-quality and CI policy.
 *
 * Run from the repository root:
 * php tools/verify-quality-policy.php
 *
 * @package WPFormVault
 */

declare(strict_types=1);

$root        = dirname( __DIR__ );
$policy_path = $root . '/docs/architecture/quality-policy.json';
$doc_path    = $root . '/docs/architecture/engineering-quality-and-ci-policy.md';
$plan_path   = $root . '/IMPLEMENTATION_PLAN.md';

/**
 * Stop with an actionable quality-policy verification failure.
 *
 * @param string $message Failure message.
 * @return never
 */
function wpfv_quality_policy_fail( string $message ): never {
	fwrite( STDERR, "Quality-policy verification failed: {$message}\n" );
	exit( 1 );
}

/**
 * Read a required text file.
 *
 * @param string $path File path.
 * @return string
 */
function wpfv_quality_policy_read( string $path ): string {
	$contents = file_get_contents( $path );

	if ( false === $contents ) {
		wpfv_quality_policy_fail( "unable to read {$path}" );
	}

	return $contents;
}

/**
 * Require a non-empty list containing unique strings.
 *
 * @param mixed  $value Field value.
 * @param string $label Field label.
 * @return array<int, string>
 */
function wpfv_quality_policy_string_list( mixed $value, string $label ): array {
	if ( ! is_array( $value ) || array() === $value ) {
		wpfv_quality_policy_fail( "{$label} must be a non-empty list" );
	}

	foreach ( $value as $item ) {
		if ( ! is_string( $item ) || '' === trim( $item ) ) {
			wpfv_quality_policy_fail( "{$label} must contain non-empty strings" );
		}
	}

	if ( count( $value ) !== count( array_unique( $value ) ) ) {
		wpfv_quality_policy_fail( "{$label} must not contain duplicates" );
	}

	return array_values( $value );
}

/**
 * Verify that all expected strings are present.
 *
 * @param array<int, string> $actual Actual list.
 * @param array<int, string> $expected Required values.
 * @param string             $label List label.
 * @return void
 */
function wpfv_quality_policy_require_values( array $actual, array $expected, string $label ): void {
	$missing = array_values( array_diff( $expected, $actual ) );

	if ( array() !== $missing ) {
		wpfv_quality_policy_fail( "{$label} is missing: " . implode( ', ', $missing ) );
	}
}

$policy_json = wpfv_quality_policy_read( $policy_path );

try {
	$policy = json_decode( $policy_json, true, 512, JSON_THROW_ON_ERROR );
} catch ( JsonException $exception ) {
	wpfv_quality_policy_fail( 'invalid policy JSON: ' . $exception->getMessage() );
}

if ( ! is_array( $policy ) || 1 !== ( $policy['schema_version'] ?? null ) ) {
	wpfv_quality_policy_fail( 'schema_version must be integer 1' );
}

if (
	'ARCH-005' !== ( $policy['owning_task'] ?? null )
	|| 'QA-001' !== ( $policy['implementation_task'] ?? null )
) {
	wpfv_quality_policy_fail( 'policy ownership must remain ARCH-005 with QA-001 implementing the tooling' );
}

$allowed_statuses = array(
	'policy_accepted_tooling_not_implemented',
	'policy_implemented',
);

if ( ! in_array( $policy['status'] ?? null, $allowed_statuses, true ) ) {
	wpfv_quality_policy_fail( 'status must state whether QA-001 has implemented the accepted policy' );
}

if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $policy['researched_on'] ?? '' ) ) {
	wpfv_quality_policy_fail( 'researched_on must use YYYY-MM-DD' );
}

$snapshot = $policy['reference_snapshot'] ?? null;

if ( ! is_array( $snapshot ) ) {
	wpfv_quality_policy_fail( 'reference_snapshot must be an object' );
}

if ( 1 !== preg_match( '/^\d+\.\d+\.\d+$/', $snapshot['wordpress_latest_stable'] ?? '' ) ) {
	wpfv_quality_policy_fail( 'reference WordPress latest stable must be an exact semantic version' );
}

$supported_php = wpfv_quality_policy_string_list(
	$snapshot['php_currently_supported'] ?? null,
	'reference_snapshot.php_currently_supported'
);
$legacy_php    = wpfv_quality_policy_string_list(
	$snapshot['php_plugin_legacy_coverage'] ?? null,
	'reference_snapshot.php_plugin_legacy_coverage'
);

if ( in_array( '8.1', $supported_php, true ) || ! in_array( '8.1', $legacy_php, true ) ) {
	wpfv_quality_policy_fail( 'PHP 8.1 must be identified as plugin legacy coverage, not current upstream support' );
}

if (
	9 !== ( $snapshot['phpunit_common_runner_major'] ?? null )
	|| '6.5-7.0' !== ( $snapshot['wordpress_phpunit_matrix_range'] ?? null )
) {
	wpfv_quality_policy_fail( 'PHPUnit major 9 must serve the official WordPress 6.5-7.0 test-harness range' );
}

$platform = $policy['platform_contract'] ?? null;

if (
	! is_array( $platform )
	|| '6.5' !== ( $platform['wordpress_minimum'] ?? null )
	|| '8.1' !== ( $platform['php_minimum'] ?? null )
	|| true !== ( $platform['php_64_bit_required'] ?? null )
	|| '5.7' !== ( $platform['mysql_minimum'] ?? null )
	|| '10.4' !== ( $platform['mariadb_minimum'] ?? null )
	|| true !== ( $platform['rolling_targets_resolve_at_run'] ?? null )
	|| true !== ( $platform['rolling_resolution_evidence_required'] ?? null )
) {
	wpfv_quality_policy_fail( 'platform contract differs from the frozen compatibility baseline' );
}

$coding = $policy['coding_standard'] ?? null;

if (
	! is_array( $coding )
	|| 'PHP_CodeSniffer' !== ( $coding['engine'] ?? null )
	|| '8.1-' !== ( $coding['php_compatibility_test_version'] ?? null )
	|| false !== ( $coding['new_errors_allowed'] ?? null )
	|| true !== ( $coding['inline_suppression_requires_reason'] ?? null )
) {
	wpfv_quality_policy_fail( 'coding-standard engine, PHP range, or suppression policy changed' );
}

$rulesets = wpfv_quality_policy_string_list( $coding['rulesets'] ?? null, 'coding_standard.rulesets' );
wpfv_quality_policy_require_values(
	$rulesets,
	array( 'WordPress-Core', 'WordPress-Docs', 'WordPress-Extra', 'PHPCompatibilityWP' ),
	'coding_standard.rulesets'
);

$coding_paths = wpfv_quality_policy_string_list( $coding['scan_paths'] ?? null, 'coding_standard.scan_paths' );
wpfv_quality_policy_require_values(
	$coding_paths,
	array( 'wp-formvault.php', 'includes', 'tools', 'tests' ),
	'coding_standard.scan_paths'
);

$excluded_paths = wpfv_quality_policy_string_list(
	$coding['exclude_paths'] ?? null,
	'coding_standard.exclude_paths'
);
wpfv_quality_policy_require_values(
	$excluded_paths,
	array( 'vendor', 'vendor-prefixed', 'libraries', 'build', 'dist' ),
	'coding_standard.exclude_paths'
);

$analysis = $policy['static_analysis'] ?? null;

if (
	! is_array( $analysis )
	|| 'PHPStan' !== ( $analysis['engine'] ?? null )
	|| 8 !== ( $analysis['level'] ?? null )
	|| 'forbidden' !== ( $analysis['baseline_policy'] ?? null )
	|| true !== ( $analysis['generated_and_third_party_excluded'] ?? null )
	|| true !== ( $analysis['unmatched_ignores_reported'] ?? null )
) {
	wpfv_quality_policy_fail( 'PHPStan level-8 no-baseline contract changed' );
}

$analysis_paths = wpfv_quality_policy_string_list(
	$analysis['scan_paths'] ?? null,
	'static_analysis.scan_paths'
);
wpfv_quality_policy_require_values(
	$analysis_paths,
	array( 'wp-formvault.php', 'includes' ),
	'static_analysis.scan_paths'
);

$stub_domains = wpfv_quality_policy_string_list(
	$analysis['stub_domains'] ?? null,
	'static_analysis.stub_domains'
);
wpfv_quality_policy_require_values(
	$stub_domains,
	array( 'WordPress', 'Action Scheduler' ),
	'static_analysis.stub_domains'
);

$test_policy = $policy['test_policy'] ?? null;

if (
	! is_array( $test_policy )
	|| 'PHPUnit' !== ( $test_policy['runner'] ?? null )
	|| 9 !== ( $test_policy['runner_major_for_all_php_lanes'] ?? null )
	|| true !== ( $test_policy['production_data_forbidden'] ?? null )
	|| 'forbidden' !== ( $test_policy['network_calls_default'] ?? null )
	|| 'dedicated_ephemeral_database' !== ( $test_policy['database_isolation'] ?? null )
) {
	wpfv_quality_policy_fail( 'test runner, isolation, network, or data-safety contract changed' );
}

$test_layout = $test_policy['layout'] ?? null;

if ( ! is_array( $test_layout ) ) {
	wpfv_quality_policy_fail( 'test_policy.layout must be a list' );
}

$layout_by_path = array();

foreach ( $test_layout as $entry ) {
	if (
		! is_array( $entry )
		|| ! is_string( $entry['path'] ?? null )
		|| ! is_bool( $entry['wordpress_bootstrap'] ?? null )
		|| ! is_string( $entry['purpose'] ?? null )
		|| '' === trim( $entry['purpose'] )
	) {
		wpfv_quality_policy_fail( 'every test layout entry needs path, wordpress_bootstrap, and purpose' );
	}

	if ( isset( $layout_by_path[ $entry['path'] ] ) ) {
		wpfv_quality_policy_fail( "duplicate test layout path: {$entry['path']}" );
	}

	$layout_by_path[ $entry['path'] ] = $entry;
}

$required_layout = array(
	'tests/Unit'        => false,
	'tests/Integration' => true,
	'tests/Functional'  => true,
	'tests/Security'    => true,
	'tests/Performance' => true,
	'tests/Support'     => false,
	'tests/Fixtures'    => false,
);

foreach ( $required_layout as $path => $requires_wordpress ) {
	if (
		! isset( $layout_by_path[ $path ] )
		|| $requires_wordpress !== $layout_by_path[ $path ]['wordpress_bootstrap']
	) {
		wpfv_quality_policy_fail( "test layout contract is missing or changed: {$path}" );
	}
}

$ci = $policy['ci_policy'] ?? null;

if (
	! is_array( $ci )
	|| true !== ( $ci['unimplemented_required_lane_is_failure'] ?? null )
	|| true !== ( $ci['exact_resolved_versions_logged'] ?? null )
	|| true !== ( $ci['release_requires_all_blocking_lanes'] ?? null )
	|| 'record_or_link_a_bug_before_the_next_release' !== ( $ci['nonblocking_failure_policy'] ?? null )
) {
	wpfv_quality_policy_fail( 'CI evidence, missing-lane, release, or nightly-failure policy changed' );
}

$required_lanes = $ci['required_lanes'] ?? null;

if ( ! is_array( $required_lanes ) ) {
	wpfv_quality_policy_fail( 'ci_policy.required_lanes must be a list' );
}

$lanes_by_id = array();

foreach ( $required_lanes as $lane ) {
	if (
		! is_array( $lane )
		|| ! is_string( $lane['id'] ?? null )
		|| ! is_string( $lane['cadence'] ?? null )
		|| ! is_bool( $lane['blocking'] ?? null )
		|| ! array_key_exists( 'wordpress', $lane )
		|| ! array_key_exists( 'php', $lane )
		|| ! array_key_exists( 'database', $lane )
		|| ! is_string( $lane['site_mode'] ?? null )
	) {
		wpfv_quality_policy_fail( 'every CI lane needs identity, cadence, blocking state, platform, and site mode' );
	}

	wpfv_quality_policy_string_list( $lane['suites'] ?? null, "CI lane {$lane['id']} suites" );

	if ( isset( $lanes_by_id[ $lane['id'] ] ) ) {
		wpfv_quality_policy_fail( "duplicate CI lane: {$lane['id']}" );
	}

	$lanes_by_id[ $lane['id'] ] = $lane;
}

$expected_lanes = array(
	'quality-minimum'                       => true,
	'quality-latest'                        => true,
	'integration-minimum-mysql'             => true,
	'integration-minimum-mariadb'           => true,
	'integration-current-php-band'          => true,
	'integration-current-mariadb-multisite' => true,
	'dependency-build-minimum'              => true,
	'wordpress-trunk-forward-compatibility' => false,
	'performance-release-candidate'         => true,
);

foreach ( $expected_lanes as $lane_id => $blocking ) {
	if ( ! isset( $lanes_by_id[ $lane_id ] ) ) {
		wpfv_quality_policy_fail( "required CI lane is missing: {$lane_id}" );
	}

	if ( $blocking !== $lanes_by_id[ $lane_id ]['blocking'] ) {
		wpfv_quality_policy_fail( "CI lane blocking state changed: {$lane_id}" );
	}
}

$minimum_mysql   = $lanes_by_id['integration-minimum-mysql'];
$minimum_mariadb = $lanes_by_id['integration-minimum-mariadb'];

if (
	'6.5.0' !== $minimum_mysql['wordpress']
	|| '8.1' !== $minimum_mysql['php']
	|| 'mysql:5.7' !== $minimum_mysql['database']
	|| 'single' !== $minimum_mysql['site_mode']
) {
	wpfv_quality_policy_fail( 'minimum MySQL CI lane differs from the advertised floor' );
}

if (
	'6.5.0' !== $minimum_mariadb['wordpress']
	|| '8.1' !== $minimum_mariadb['php']
	|| 'mariadb:10.4' !== $minimum_mariadb['database']
	|| 'single' !== $minimum_mariadb['site_mode']
) {
	wpfv_quality_policy_fail( 'minimum MariaDB CI lane differs from the advertised floor' );
}

$current_php_band = $lanes_by_id['integration-current-php-band']['php'];

if ( $supported_php !== $current_php_band ) {
	wpfv_quality_policy_fail( 'current integration PHP band must match the dated upstream-support snapshot' );
}

if (
	'latest-stable' !== $lanes_by_id['integration-current-php-band']['wordpress']
	|| 'trunk' !== $lanes_by_id['wordpress-trunk-forward-compatibility']['wordpress']
	|| 'nightly' !== $lanes_by_id['wordpress-trunk-forward-compatibility']['cadence']
) {
	wpfv_quality_policy_fail( 'rolling stable/trunk WordPress lane contract changed' );
}

$document = wpfv_quality_policy_read( $doc_path );

$required_document_contracts = array(
	'`QA-001` installed the locked analyzers and test runner',
	'PHPStan runs at **level 8**',
	'PHPUnit 9.6 is the common runner',
	'no generated PHPStan baseline',
	'Fixtures must never contain secrets',
	'A skipped, cancelled, unresolved, or missing blocking lane is a failure',
	'PHP 8.1 remains a blocking lane while WP FormVault advertises it',
	'`QA-001` must implement it without weakening it',
);

foreach ( $required_document_contracts as $contract ) {
	if ( ! str_contains( $document, $contract ) ) {
		wpfv_quality_policy_fail( "engineering policy is missing stable contract: {$contract}" );
	}
}

$plan = wpfv_quality_policy_read( $plan_path );

$required_plan_contracts = array(
	'### 35.7 Engineering quality and CI contract **[NEW]**',
	'PHPStan level 8',
	'`docs/architecture/quality-policy.json`',
	'Missing, skipped, cancelled, or unresolved blocking lanes fail the release gate.',
);

foreach ( $required_plan_contracts as $contract ) {
	if ( ! str_contains( $plan, $contract ) ) {
		wpfv_quality_policy_fail( "implementation plan is missing stable contract: {$contract}" );
	}
}

printf(
	"WP FormVault quality-policy verification passed: %d coding rulesets, PHPStan level %d, %d test areas, %d CI lanes (%d blocking), reference snapshot %s.\n",
	count( $rulesets ),
	$analysis['level'],
	count( $layout_by_path ),
	count( $lanes_by_id ),
	count( array_filter( $expected_lanes ) ),
	$policy['researched_on']
);
