<?php
/**
 * Database boundary for schema coordination.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Restricts schema services to reviewed database operations.
 */
interface SchemaDatabaseInterface {

	/**
	 * Current site's WordPress table prefix.
	 */
	public function table_prefix(): string;

	/**
	 * Current site's reviewed charset/collation clause.
	 */
	public function charset_collate(): string;

	/**
	 * Apply table definitions through WordPress dbDelta.
	 *
	 * @param array<string> $statements Complete CREATE TABLE statements.
	 */
	public function apply_schema( array $statements ): void;

	/**
	 * Whether an exact table name exists.
	 *
	 * @param string $table_name Validated physical table name.
	 */
	public function table_exists( string $table_name ): bool;

	/**
	 * Return declared SQL column types keyed by column name.
	 *
	 * @param string $table_name Validated physical table name.
	 * @return array<string, string>
	 */
	public function table_columns( string $table_name ): array;

	/**
	 * Return indexes keyed by name.
	 *
	 * @param string $table_name Validated physical table name.
	 * @return array<string, array{unique:bool, columns:array<int, string>}>
	 */
	public function table_indexes( string $table_name ): array;

	/**
	 * Prepare a SQL statement using WordPress placeholder handling.
	 *
	 * @param string       $query SQL with placeholders.
	 * @param array<mixed> $arguments Bound scalar values.
	 */
	public function prepare( string $query, array $arguments ): string;

	/**
	 * Execute a reviewed SQL write and return affected rows.
	 *
	 * @param string $query Prepared or constant SQL.
	 */
	public function execute( string $query ): int;

	/**
	 * Fetch one associative row.
	 *
	 * @param string $query Prepared or constant SQL.
	 * @return array<string, mixed>|null
	 */
	public function fetch_row( string $query ): ?array;
}
