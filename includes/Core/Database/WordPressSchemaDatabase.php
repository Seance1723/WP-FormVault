<?php
/**
 * WordPress database boundary for schema coordination.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Database;

use WPFormVault\Core\Exception\SchemaException;
use wpdb;

defined( 'ABSPATH' ) || exit;

/**
 * Adapts the current site's wpdb instance to reviewed schema operations.
 */
final class WordPressSchemaDatabase implements SchemaDatabaseInterface {

	/**
	 * Current WordPress database connection.
	 *
	 * @var wpdb
	 */
	private wpdb $wpdb;

	/**
	 * Store the current site's database connection.
	 *
	 * @param wpdb $wpdb WordPress database connection.
	 */
	public function __construct( wpdb $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * Current site's WordPress table prefix.
	 */
	public function table_prefix(): string {
		return (string) $this->wpdb->prefix;
	}

	/**
	 * Current site's charset/collation clause.
	 */
	public function charset_collate(): string {
		return trim( $this->wpdb->get_charset_collate() );
	}

	/**
	 * Apply reviewed table definitions through dbDelta.
	 *
	 * @param array<string> $statements Complete CREATE TABLE statements.
	 * @throws SchemaException When WordPress's schema utility is unavailable.
	 */
	public function apply_schema( array $statements ): void {
		$upgrade_path = ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( ! function_exists( 'dbDelta' ) ) {
			if ( ! is_readable( $upgrade_path ) ) {
				throw new SchemaException( 'schema_upgrade_api_unavailable' );
			}

			require_once $upgrade_path;
		}

		if ( ! function_exists( 'dbDelta' ) ) {
			throw new SchemaException( 'schema_upgrade_api_unavailable' );
		}

		dbDelta( $statements );
	}

	/**
	 * Whether an exact table name exists.
	 *
	 * @param string $table_name Validated physical table name.
	 */
	public function table_exists( string $table_name ): bool {
		$this->assert_table_name( $table_name );

		$like  = $this->wpdb->esc_like( $table_name );
		$query = $this->prepare( 'SHOW TABLES LIKE %s', array( $like ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared by wpdb immediately above.
		return $table_name === $this->wpdb->get_var( $query );
	}

	/**
	 * Return SQL column types keyed by name.
	 *
	 * @param string $table_name Validated physical table name.
	 * @return array<string, string>
	 * @throws SchemaException When the metadata query fails.
	 */
	public function table_columns( string $table_name ): array {
		$this->assert_table_name( $table_name );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier is allow-list validated.
		$rows = $this->wpdb->get_results( "SHOW COLUMNS FROM {$table_name}", 'ARRAY_A' );

		if ( ! is_array( $rows ) ) {
			throw new SchemaException( 'schema_metadata_read_failed' );
		}

		$columns = array();

		foreach ( $rows as $row ) {
			$name = $row['Field'] ?? null;
			$type = $row['Type'] ?? null;

			if ( ! is_string( $name ) || ! is_string( $type ) ) {
				throw new SchemaException( 'schema_metadata_invalid' );
			}

			$columns[ $name ] = strtolower( $type );
		}

		return $columns;
	}

	/**
	 * Return indexes keyed by name.
	 *
	 * @param string $table_name Validated physical table name.
	 * @return array<string, array{unique:bool, columns:array<int, string>}>
	 * @throws SchemaException When the metadata query fails.
	 */
	public function table_indexes( string $table_name ): array {
		$this->assert_table_name( $table_name );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier is allow-list validated.
		$rows = $this->wpdb->get_results( "SHOW INDEX FROM {$table_name}", 'ARRAY_A' );

		if ( ! is_array( $rows ) ) {
			throw new SchemaException( 'schema_metadata_read_failed' );
		}

		$indexes = array();

		foreach ( $rows as $row ) {
			$name       = $row['Key_name'] ?? null;
			$column     = $row['Column_name'] ?? null;
			$sequence   = $row['Seq_in_index'] ?? null;
			$non_unique = $row['Non_unique'] ?? null;

			if (
				! is_string( $name )
				|| ! is_string( $column )
				|| ! is_numeric( $sequence )
				|| ! is_numeric( $non_unique )
			) {
				throw new SchemaException( 'schema_metadata_invalid' );
			}

			if ( ! isset( $indexes[ $name ] ) ) {
				$indexes[ $name ] = array(
					'unique'  => 0 === (int) $non_unique,
					'columns' => array(),
				);
			}

			$indexes[ $name ]['columns'][ (int) $sequence ] = $column;
		}

		foreach ( $indexes as &$index ) {
			ksort( $index['columns'] );
			$index['columns'] = array_values( $index['columns'] );
		}
		unset( $index );

		return $indexes;
	}

	/**
	 * Prepare a SQL statement with WordPress placeholders.
	 *
	 * @param string       $query     SQL with placeholders.
	 * @param array<mixed> $arguments Bound scalar values.
	 * @throws SchemaException When preparation fails.
	 */
	public function prepare( string $query, array $arguments ): string {
		// @phpstan-ignore argument.type (Validated table identifiers make the reviewed query dynamic.)
		$prepared = $this->wpdb->prepare( $query, $arguments ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- This reviewed boundary receives arguments separately.

		if ( ! is_string( $prepared ) ) {
			throw new SchemaException( 'schema_query_prepare_failed' );
		}

		return $prepared;
	}

	/**
	 * Execute a reviewed SQL write.
	 *
	 * @param string $query Prepared or constant SQL.
	 * @throws SchemaException When the database rejects the write.
	 */
	public function execute( string $query ): int {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Callers pass constants or wpdb-prepared statements.
		$result = $this->wpdb->query( $query );

		if ( false === $result ) {
			throw new SchemaException( 'schema_database_write_failed' );
		}

		return true === $result ? 1 : $result;
	}

	/**
	 * Fetch one associative row.
	 *
	 * @param string $query Prepared or constant SQL.
	 * @return array<string, mixed>|null
	 * @throws SchemaException When the database query fails.
	 */
	public function fetch_row( string $query ): ?array {
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Callers pass constants or wpdb-prepared statements.
		$row = $this->wpdb->get_row( $query, 'ARRAY_A' );

		if ( null === $row ) {
			if ( '' !== (string) $this->wpdb->last_error ) {
				throw new SchemaException( 'schema_database_read_failed' );
			}

			return null;
		}

		return $row;
	}

	/**
	 * Reject any physical identifier outside WordPress's prefix alphabet.
	 *
	 * @param string $table_name Physical table name.
	 * @throws SchemaException When the identifier is unsafe.
	 */
	private function assert_table_name( string $table_name ): void {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]+$/D', $table_name ) ) {
			throw new SchemaException( 'schema_table_name_invalid' );
		}
	}
}
