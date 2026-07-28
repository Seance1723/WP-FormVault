<?php
/**
 * Frozen schema-control table definitions.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Database;

use WPFormVault\Core\Exception\SchemaException;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves and describes the per-site migration control plane.
 */
final class ControlPlaneSchema {

	public const SCHEMA_SUFFIX = 'schema_version';

	public const LOCKS_SUFFIX = 'locks';

	/**
	 * Physical schema-state table.
	 *
	 * @var string
	 */
	private string $schema_table;

	/**
	 * Physical lease table.
	 *
	 * @var string
	 */
	private string $locks_table;

	/**
	 * Resolve current-site physical names.
	 *
	 * @param string $site_prefix   Current WordPress site prefix.
	 * @param string $plugin_prefix Frozen WP FormVault suffix prefix.
	 * @throws SchemaException When either prefix is unsafe.
	 */
	public function __construct( string $site_prefix, string $plugin_prefix ) {
		if (
			1 !== preg_match( '/^[A-Za-z0-9_]+$/D', $site_prefix )
			|| 'wpfv_' !== $plugin_prefix
		) {
			throw new SchemaException( 'schema_table_prefix_invalid' );
		}

		$this->schema_table = $site_prefix . $plugin_prefix . self::SCHEMA_SUFFIX;
		$this->locks_table  = $site_prefix . $plugin_prefix . self::LOCKS_SUFFIX;
	}

	/**
	 * Physical schema-state table.
	 */
	public function schema_table(): string {
		return $this->schema_table;
	}

	/**
	 * Physical lease table.
	 */
	public function locks_table(): string {
		return $this->locks_table;
	}

	/**
	 * Database-delta-compatible control-plane definitions.
	 *
	 * @param string $charset_collate Current site's charset/collation clause.
	 * @return array<string>
	 */
	public function create_statements( string $charset_collate ): array {
		$charset_collate = '' === $charset_collate ? '' : ' ' . trim( $charset_collate );

		$schema_sql = "CREATE TABLE {$this->schema_table} (
id tinyint(3) unsigned NOT NULL,
installed_version bigint(20) unsigned NOT NULL DEFAULT 0,
target_version bigint(20) unsigned NOT NULL DEFAULT 0,
state varchar(32) NOT NULL,
current_migration varchar(191) DEFAULT NULL,
run_id char(36) DEFAULT NULL,
started_at datetime DEFAULT NULL,
heartbeat_at datetime DEFAULT NULL,
completed_at datetime DEFAULT NULL,
failed_at datetime DEFAULT NULL,
retry_count int(10) unsigned NOT NULL DEFAULT 0,
last_error_code varchar(64) DEFAULT NULL,
last_error_at datetime DEFAULT NULL,
row_version bigint(20) unsigned NOT NULL DEFAULT 0,
updated_at datetime NOT NULL,
PRIMARY KEY  (id)
){$charset_collate};";

		$locks_sql = "CREATE TABLE {$this->locks_table} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
lock_key varchar(191) NOT NULL,
owner_token_hash binary(32) NOT NULL,
owner_context varchar(32) NOT NULL,
acquired_at datetime NOT NULL,
heartbeat_at datetime NOT NULL,
expires_at datetime NOT NULL,
fencing_token bigint(20) unsigned NOT NULL DEFAULT 0,
metadata_json longtext DEFAULT NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY lock_key (lock_key)
){$charset_collate};";

		return array( $schema_sql, $locks_sql );
	}

	/**
	 * Required columns and portable type patterns by physical table.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function required_columns(): array {
		return array(
			$this->schema_table => array(
				'id'                => '/^tinyint(?:\(3\))? unsigned$/D',
				'installed_version' => '/^bigint(?:\(20\))? unsigned$/D',
				'target_version'    => '/^bigint(?:\(20\))? unsigned$/D',
				'state'             => '/^varchar\(32\)$/D',
				'current_migration' => '/^varchar\(191\)$/D',
				'run_id'            => '/^char\(36\)$/D',
				'started_at'        => '/^datetime$/D',
				'heartbeat_at'      => '/^datetime$/D',
				'completed_at'      => '/^datetime$/D',
				'failed_at'         => '/^datetime$/D',
				'retry_count'       => '/^int(?:\(10\))? unsigned$/D',
				'last_error_code'   => '/^varchar\(64\)$/D',
				'last_error_at'     => '/^datetime$/D',
				'row_version'       => '/^bigint(?:\(20\))? unsigned$/D',
				'updated_at'        => '/^datetime$/D',
			),
			$this->locks_table  => array(
				'id'               => '/^bigint(?:\(20\))? unsigned$/D',
				'lock_key'         => '/^varchar\(191\)$/D',
				'owner_token_hash' => '/^binary\(32\)$/D',
				'owner_context'    => '/^varchar\(32\)$/D',
				'acquired_at'      => '/^datetime$/D',
				'heartbeat_at'     => '/^datetime$/D',
				'expires_at'       => '/^datetime$/D',
				'fencing_token'    => '/^bigint(?:\(20\))? unsigned$/D',
				'metadata_json'    => '/^longtext$/D',
				'created_at'       => '/^datetime$/D',
				'updated_at'       => '/^datetime$/D',
			),
		);
	}

	/**
	 * Required primary and unique keys by physical table.
	 *
	 * @return array<string, array<string, array{unique:bool, columns:array<int, string>}>>
	 */
	public function required_indexes(): array {
		return array(
			$this->schema_table => array(
				'PRIMARY' => array(
					'unique'  => true,
					'columns' => array( 'id' ),
				),
			),
			$this->locks_table  => array(
				'PRIMARY'  => array(
					'unique'  => true,
					'columns' => array( 'id' ),
				),
				'lock_key' => array(
					'unique'  => true,
					'columns' => array( 'lock_key' ),
				),
			),
		);
	}
}
