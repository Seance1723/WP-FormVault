<?php
/**
 * Verify the accepted database inventory and migration-state contract.
 *
 * Run from the repository root:
 * php tools/verify-database-schema-policy.php
 *
 * @package WPFormVault
 */

declare(strict_types=1);

$root        = dirname( __DIR__ );
$policy_path = $root . '/docs/architecture/database-schema-policy.json';
$doc_path    = $root . '/docs/architecture/database-schema-and-migration-state.md';
$plan_path   = $root . '/IMPLEMENTATION_PLAN.md';

/**
 * Stop with an actionable database-policy verification failure.
 *
 * @param string $message Failure message.
 * @return never
 */
function wpfv_database_schema_fail( string $message ): never {
	fwrite( STDERR, "Database schema policy verification failed: {$message}\n" );
	exit( 1 );
}

/**
 * Assert that a table key references declared columns.
 *
 * @param string               $table_name Table suffix.
 * @param string               $key_name   Key label for diagnostics.
 * @param mixed                $key         Candidate key.
 * @param array<string, array> $columns     Declared columns keyed by name.
 */
function wpfv_database_schema_assert_key(
	string $table_name,
	string $key_name,
	mixed $key,
	array $columns
): void {
	if ( ! is_array( $key ) || array() === $key ) {
		wpfv_database_schema_fail( "{$table_name} {$key_name} must be a non-empty column list" );
	}

	if ( count( $key ) !== count( array_unique( $key ) ) ) {
		wpfv_database_schema_fail( "{$table_name} {$key_name} contains duplicate columns" );
	}

	foreach ( $key as $column_name ) {
		if ( ! is_string( $column_name ) || ! isset( $columns[ $column_name ] ) ) {
			wpfv_database_schema_fail(
				"{$table_name} {$key_name} references a non-string or unknown column"
			);
		}
	}
}

/**
 * Determine whether a table declares an exact candidate key.
 *
 * @param array<string, mixed> $table Table definition.
 * @param array<string>        $key   Expected ordered columns.
 */
function wpfv_database_schema_has_unique_key( array $table, array $key ): bool {
	$unique_keys = $table['unique_keys'] ?? null;

	if ( ! is_array( $unique_keys ) ) {
		return false;
	}

	foreach ( $unique_keys as $candidate ) {
		if ( $candidate === $key ) {
			return true;
		}
	}

	return false;
}

/**
 * Assert that a table contains required columns.
 *
 * @param string               $table_name Table suffix.
 * @param array<string, array> $columns     Declared columns keyed by name.
 * @param array<string>        $required    Required column names.
 */
function wpfv_database_schema_require_columns(
	string $table_name,
	array $columns,
	array $required
): void {
	foreach ( $required as $column_name ) {
		if ( ! isset( $columns[ $column_name ] ) ) {
			wpfv_database_schema_fail( "{$table_name} is missing required column {$column_name}" );
		}
	}
}

$policy_json = file_get_contents( $policy_path );

if ( false === $policy_json ) {
	wpfv_database_schema_fail( "unable to read {$policy_path}" );
}

try {
	$policy = json_decode( $policy_json, true, 512, JSON_THROW_ON_ERROR );
} catch ( JsonException $exception ) {
	wpfv_database_schema_fail( 'invalid policy JSON: ' . $exception->getMessage() );
}

if ( ! is_array( $policy ) || 1 !== ( $policy['contract_version'] ?? null ) ) {
	wpfv_database_schema_fail( 'contract_version must be integer 1' );
}

if ( 'DB-001' !== ( $policy['owning_task'] ?? null ) ) {
	wpfv_database_schema_fail( 'policy must be owned by DB-001' );
}

$required_top_level = array(
	'product'                  => 'WP FormVault',
	'physical_name_template'   => '{$wpdb->prefix}wpfv_{suffix}',
	'site_scope'               => 'per_site',
	'timestamp_storage'        => 'UTC',
	'charset_collation_source' => '$wpdb->get_charset_collate()',
);

foreach ( $required_top_level as $key => $expected ) {
	if ( ( $policy[ $key ] ?? null ) !== $expected ) {
		wpfv_database_schema_fail( "{$key} has drifted from its required value" );
	}
}

$json_storage = $policy['json_storage'] ?? null;

if (
	! is_array( $json_storage )
	|| 'longtext' !== ( $json_storage['sql_type'] ?? null )
	|| 'application' !== ( $json_storage['validation'] ?? null )
) {
	wpfv_database_schema_fail( 'JSON storage must use application-validated longtext' );
}

$relation_policy = $policy['relations'] ?? null;

if (
	! is_array( $relation_policy )
	|| 'application' !== ( $relation_policy['enforcement'] ?? null )
	|| false !== ( $relation_policy['database_foreign_keys'] ?? null )
	|| ! is_array( $relation_policy['delete_actions'] ?? null )
) {
	wpfv_database_schema_fail( 'relations must be application-enforced without database foreign keys' );
}

$delete_actions   = $relation_policy['delete_actions'];
$expected_deletes = array( 'cascade', 'restrict', 'set_null', 'anonymize' );

if ( $expected_deletes !== $delete_actions ) {
	wpfv_database_schema_fail( 'delete_actions contract has drifted' );
}

$type_profiles = $policy['type_profiles'] ?? null;

if ( ! is_array( $type_profiles ) || array() === $type_profiles ) {
	wpfv_database_schema_fail( 'type_profiles must be a non-empty object' );
}

foreach ( $type_profiles as $profile_name => $profile ) {
	if (
		! is_string( $profile_name )
		|| 1 !== preg_match( '/^[a-z][a-z0-9_]*$/', $profile_name )
		|| ! is_array( $profile )
	) {
		wpfv_database_schema_fail( 'invalid type profile definition' );
	}

	$sql      = $profile['sql'] ?? null;
	$nullable = $profile['nullable'] ?? null;

	if ( ! is_string( $sql ) || '' === $sql || strtolower( $sql ) !== $sql ) {
		wpfv_database_schema_fail( "type profile {$profile_name} must use a lowercase SQL type" );
	}

	if ( preg_match( '/\bjson\b/', $sql ) ) {
		wpfv_database_schema_fail( "type profile {$profile_name} cannot depend on a native JSON type" );
	}

	if ( ! is_bool( $nullable ) ) {
		wpfv_database_schema_fail( "type profile {$profile_name} nullable must be boolean" );
	}

	if (
		str_contains( $sql, 'datetime' )
		&& 'UTC' !== ( $profile['timezone'] ?? null )
	) {
		wpfv_database_schema_fail( "datetime profile {$profile_name} must explicitly use UTC" );
	}
}

$expected_tables = array(
	'access_grants',
	'audit_logs',
	'automation_actions',
	'automation_rules',
	'download_logs',
	'download_tokens',
	'form_fields',
	'forms',
	'jobs',
	'locks',
	'notification_prefs',
	'notifications',
	'report_deliveries',
	'report_files',
	'report_records',
	'report_templates',
	'reports',
	'saved_views',
	'schedule_fields',
	'schedule_filters',
	'schedule_forms',
	'schedule_mappings',
	'schedule_recipients',
	'schedules',
	'schema_version',
	'submission_notes',
	'submission_snapshot',
	'submission_tags',
	'submission_values',
	'submission_workflow',
	'submissions',
	'sync_cursors',
	'sync_logs',
	'tags',
);
$tables          = $policy['tables'] ?? null;

if ( ! is_array( $tables ) ) {
	wpfv_database_schema_fail( 'tables must be an object' );
}

$actual_tables = array_keys( $tables );
sort( $actual_tables );

if ( $expected_tables !== $actual_tables ) {
	wpfv_database_schema_fail(
		'table inventory differs from the required 34 suffixes; found [' . implode( ', ', $actual_tables ) . ']'
	);
}

$column_maps      = array();
$column_count     = 0;
$relation_count   = 0;
$unique_key_count = 0;
$owner_groups     = array(
	'DB-002' => 'control',
	'DB-003' => 'submission_index',
	'DB-004' => 'reporting_and_schedules',
	'DB-005' => 'workflow_and_automation',
	'DB-006' => 'operations_and_access',
);

foreach ( $tables as $table_name => $table ) {
	if (
		1 !== preg_match( '/^[a-z][a-z0-9_]*$/', $table_name )
		|| str_starts_with( $table_name, 'wp_' )
		|| str_starts_with( $table_name, 'wpfv_' )
	) {
		wpfv_database_schema_fail( "unsafe or pre-prefixed table suffix {$table_name}" );
	}

	if ( ! is_array( $table ) ) {
		wpfv_database_schema_fail( "table {$table_name} must be an object" );
	}

	$group     = $table['group'] ?? null;
	$owner     = $table['owner'] ?? null;
	$purpose   = $table['purpose'] ?? null;
	$columns   = $table['columns'] ?? null;
	$primary   = $table['primary_key'] ?? null;
	$uniques   = $table['unique_keys'] ?? null;
	$relations = $table['relations'] ?? null;

	if ( ! is_string( $group ) || '' === $group ) {
		wpfv_database_schema_fail( "table {$table_name} must declare its group" );
	}

	if ( ! is_string( $owner ) || ! isset( $owner_groups[ $owner ] ) ) {
		wpfv_database_schema_fail( "table {$table_name} has an invalid owner" );
	}

	if ( $owner_groups[ $owner ] !== $group ) {
		wpfv_database_schema_fail( "table {$table_name} group does not match owner {$owner}" );
	}

	if ( ! is_string( $purpose ) || '' === trim( $purpose ) ) {
		wpfv_database_schema_fail( "table {$table_name} must document its purpose" );
	}

	if ( ! is_array( $columns ) || array() === $columns ) {
		wpfv_database_schema_fail( "table {$table_name} must declare columns" );
	}

	if ( ! is_array( $uniques ) || ! is_array( $relations ) ) {
		wpfv_database_schema_fail( "table {$table_name} unique_keys and relations must be arrays" );
	}

	$column_map = array();

	foreach ( $columns as $column ) {
		if ( ! is_array( $column ) ) {
			wpfv_database_schema_fail( "table {$table_name} has an invalid column definition" );
		}

		$column_name = $column['name'] ?? null;
		$profile     = $column['profile'] ?? null;

		if (
			! is_string( $column_name )
			|| 1 !== preg_match( '/^[a-z][a-z0-9_]*$/', $column_name )
			|| isset( $column_map[ $column_name ] )
		) {
			wpfv_database_schema_fail( "table {$table_name} has an invalid or duplicate column name" );
		}

		if ( ! is_string( $profile ) || ! isset( $type_profiles[ $profile ] ) ) {
			wpfv_database_schema_fail( "table {$table_name}.{$column_name} uses unknown profile" );
		}

		$column_map[ $column_name ] = $column;
		++$column_count;
	}

	wpfv_database_schema_assert_key( $table_name, 'primary_key', $primary, $column_map );

	foreach ( $uniques as $index => $unique ) {
		wpfv_database_schema_assert_key(
			$table_name,
			'unique_keys[' . (string) $index . ']',
			$unique,
			$column_map
		);
		++$unique_key_count;
	}

	$column_maps[ $table_name ] = $column_map;
}

foreach ( $tables as $table_name => $table ) {
	foreach ( $table['relations'] as $relation ) {
		if ( ! is_array( $relation ) ) {
			wpfv_database_schema_fail( "table {$table_name} has an invalid relation" );
		}

		$from        = $relation['from'] ?? null;
		$to          = $relation['to'] ?? null;
		$external_to = $relation['external_to'] ?? null;
		$on_delete   = $relation['on_delete'] ?? null;

		if ( ! is_string( $from ) || ! isset( $column_maps[ $table_name ][ $from ] ) ) {
			wpfv_database_schema_fail( "table {$table_name} relation has an unknown source column" );
		}

		if ( ( is_string( $to ) ? 1 : 0 ) + ( is_string( $external_to ) ? 1 : 0 ) !== 1 ) {
			wpfv_database_schema_fail( "table {$table_name}.{$from} must have exactly one relation target" );
		}

		if ( ! is_string( $on_delete ) || ! in_array( $on_delete, $delete_actions, true ) ) {
			wpfv_database_schema_fail( "table {$table_name}.{$from} has an invalid delete action" );
		}

		$from_profile = $column_maps[ $table_name ][ $from ]['profile'];

		if (
			in_array( $on_delete, array( 'set_null', 'anonymize' ), true )
			&& false === $type_profiles[ $from_profile ]['nullable']
		) {
			wpfv_database_schema_fail(
				"table {$table_name}.{$from} must be nullable for {$on_delete}"
			);
		}

		if ( is_string( $to ) ) {
			$target = explode( '.', $to );

			if (
				2 !== count( $target )
				|| ! isset( $column_maps[ $target[0] ][ $target[1] ] )
			) {
				wpfv_database_schema_fail( "table {$table_name}.{$from} references unknown target {$to}" );
			}

			$target_profile = $column_maps[ $target[0] ][ $target[1] ]['profile'];

			if ( $type_profiles[ $from_profile ]['sql'] !== $type_profiles[ $target_profile ]['sql'] ) {
				wpfv_database_schema_fail( "relation type mismatch: {$table_name}.{$from} -> {$to}" );
			}
		}

		++$relation_count;
	}
}

if ( 402 !== $column_count || 55 !== $relation_count || 21 !== $unique_key_count ) {
	wpfv_database_schema_fail(
		"catalog counts must remain 402 columns, 55 relations, and 21 unique keys; found {$column_count}, {$relation_count}, and {$unique_key_count}"
	);
}

$schema_sequence = $policy['schema_sequence'] ?? null;

if ( ! is_array( $schema_sequence ) || 5 !== count( $schema_sequence ) ) {
	wpfv_database_schema_fail( 'schema_sequence must define control version 0 and numbered versions 1-4' );
}

$sequenced_tables = array();

foreach ( $schema_sequence as $expected_version => $stage ) {
	if (
		! is_array( $stage )
		|| ( $stage['version'] ?? null ) !== $expected_version
		|| ! is_string( $stage['owner'] ?? null )
		|| ! is_string( $stage['name'] ?? null )
		|| ! is_array( $stage['tables'] ?? null )
		|| ( 0 !== $expected_version ) !== ( $stage['numbered_migration'] ?? null )
	) {
		wpfv_database_schema_fail( "invalid or non-contiguous schema stage {$expected_version}" );
	}

	foreach ( $stage['tables'] as $table_name ) {
		if (
			! is_string( $table_name )
			|| ! isset( $tables[ $table_name ] )
			|| isset( $sequenced_tables[ $table_name ] )
		) {
			wpfv_database_schema_fail( 'schema sequence contains an unknown or duplicate table' );
		}

		if ( $stage['owner'] !== $tables[ $table_name ]['owner'] ) {
			wpfv_database_schema_fail( "schema owner mismatch for table {$table_name}" );
		}

		$sequenced_tables[ $table_name ] = true;
	}
}

if ( count( $sequenced_tables ) !== count( $tables ) ) {
	wpfv_database_schema_fail( 'every table must occur exactly once in the schema sequence' );
}

$migration_state = $policy['migration_state'] ?? null;
$expected_states = array(
	'uninitialized',
	'pending',
	'running',
	'awaiting_background',
	'failed',
	'ready',
	'blocked_newer',
);

if (
	! is_array( $migration_state )
	|| 'schema_version' !== ( $migration_state['authoritative_table'] ?? null )
	|| 1 !== ( $migration_state['singleton_primary_key'] ?? null )
	|| 0 !== ( $migration_state['absent_table_means_installed_version'] ?? null )
	|| ( $migration_state['states'] ?? null ) !== $expected_states
	|| 'locks' !== ( $migration_state['lock_table'] ?? null )
	|| 'schema_migration' !== ( $migration_state['lock_key'] ?? null )
) {
	wpfv_database_schema_fail( 'per-site migration-state contract has drifted' );
}

wpfv_database_schema_require_columns(
	'submissions',
	$column_maps['submissions'],
	array(
		'source_plugin',
		'source_form_id',
		'source_submission_id',
		'adapter_type',
		'submitted_at',
		'indexed_at',
		'updated_at',
		'submission_status',
		'source_deleted',
		'sync_status',
		'data_hash',
		'snapshot_id',
	)
);
wpfv_database_schema_require_columns(
	'submission_workflow',
	$column_maps['submission_workflow'],
	array(
		'submission_id',
		'workflow_status',
		'priority',
		'assigned_user_id',
		'follow_up_at',
		'last_activity_at',
		'created_by',
		'updated_by',
		'row_version',
	)
);
wpfv_database_schema_require_columns(
	'reports',
	$column_maps['reports'],
	array(
		'schedule_id',
		'report_name',
		'report_type',
		'period_start',
		'period_end',
		'generated_at',
		'generated_by',
		'submission_count',
		'form_count',
		'status',
		'is_outdated',
		'outdated_reason',
		'file_expiry_at',
		'delivery_status',
		'retry_count',
		'idempotency_key',
	)
);

$required_unique_keys = array(
	'forms'             => array( 'source_plugin', 'source_form_id' ),
	'submissions'       => array( 'source_plugin', 'source_form_id', 'source_submission_id' ),
	'submission_values' => array( 'submission_id', 'field_key', 'value_position' ),
	'locks'             => array( 'lock_key' ),
	'reports'           => array( 'idempotency_key' ),
	'report_deliveries' => array( 'idempotency_key' ),
	'jobs'              => array( 'idempotency_key' ),
	'download_tokens'   => array( 'token_hash' ),
	'sync_cursors'      => array( 'source_plugin', 'source_form_id' ),
);

foreach ( $required_unique_keys as $table_name => $key ) {
	if ( ! wpfv_database_schema_has_unique_key( $tables[ $table_name ], $key ) ) {
		wpfv_database_schema_fail(
			"table {$table_name} is missing required unique key (" . implode( ', ', $key ) . ')'
		);
	}
}

if (
	isset( $column_maps['download_tokens']['token'] )
	|| ! isset( $column_maps['download_tokens']['token_hash'] )
	|| isset( $column_maps['locks']['owner_token'] )
	|| ! isset( $column_maps['locks']['owner_token_hash'] )
) {
	wpfv_database_schema_fail( 'raw download or lease tokens must never be persisted' );
}

$document = file_get_contents( $doc_path );

if ( false === $document ) {
	wpfv_database_schema_fail( "unable to read {$doc_path}" );
}

$required_document_contracts = array(
	'The JSON policy owns exact ordered columns',
	'Schema versions are monotonically increasing non-negative integers.',
	'There is no network-global schema version',
	'Fresh installation runs the same `0 → 1 → … → target` chain as upgrades.',
	'Never perform a long migration inside an unrestricted front-end request.',
	'store only `token_hash`',
	'`ready` is valid only when all of these are true:',
);

foreach ( $required_document_contracts as $contract ) {
	if ( ! str_contains( $document, $contract ) ) {
		wpfv_database_schema_fail( "architecture document is missing stable contract: {$contract}" );
	}
}

foreach ( array_keys( $tables ) as $table_name ) {
	if ( ! str_contains( $document, "wpfv_{$table_name}" ) ) {
		wpfv_database_schema_fail( "architecture document inventory is missing wpfv_{$table_name}" );
	}
}

$plan = file_get_contents( $plan_path );

if ( false === $plan ) {
	wpfv_database_schema_fail( "unable to read {$plan_path}" );
}

$required_plan_contracts = array(
	'## 6. Database Design',
	'`docs/architecture/database-schema-policy.json`',
	'## 40. Schema Migration & Versioning **[NEW]**',
	'monotonically increasing integer',
	'`blocked_newer`',
);

foreach ( $required_plan_contracts as $contract ) {
	if ( ! str_contains( $plan, $contract ) ) {
		wpfv_database_schema_fail( "implementation plan is missing stable contract: {$contract}" );
	}
}

printf(
	"WP FormVault database schema policy passed: %d tables, %d columns, %d application relations, %d unique keys, schema stages 0-4 valid.\n",
	count( $tables ),
	$column_count,
	$relation_count,
	$unique_key_count
);
