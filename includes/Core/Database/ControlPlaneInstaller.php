<?php
/**
 * Idempotent migration control-plane installer.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Database;

use WPFormVault\Core\Contracts\ClockInterface;
use WPFormVault\Core\Exception\SchemaException;

defined( 'ABSPATH' ) || exit;

/**
 * Creates, verifies, and seeds the two per-site control tables.
 */
final class ControlPlaneInstaller {

	/**
	 * Reviewed database boundary.
	 *
	 * @var SchemaDatabaseInterface
	 */
	private SchemaDatabaseInterface $database;

	/**
	 * Frozen control-plane definition.
	 *
	 * @var ControlPlaneSchema
	 */
	private ControlPlaneSchema $schema;

	/**
	 * UTC clock.
	 *
	 * @var ClockInterface
	 */
	private ClockInterface $clock;

	/**
	 * Configure the current-site installer.
	 *
	 * @param SchemaDatabaseInterface $database Reviewed database boundary.
	 * @param ControlPlaneSchema      $schema   Current-site control table names/definitions.
	 * @param ClockInterface          $clock    UTC clock.
	 */
	public function __construct(
		SchemaDatabaseInterface $database,
		ControlPlaneSchema $schema,
		ClockInterface $clock
	) {
		$this->database = $database;
		$this->schema   = $schema;
		$this->clock    = $clock;
	}

	/**
	 * Create/repair the exact control tables and seed singleton row 1.
	 *
	 * @throws SchemaException When postconditions are not satisfied.
	 */
	public function ensure(): void {
		if ( ! $this->postconditions_met() ) {
			$this->database->apply_schema(
				$this->schema->create_statements( $this->database->charset_collate() )
			);
		}

		if ( ! $this->postconditions_met() ) {
			throw new SchemaException( 'schema_control_plane_invalid' );
		}

		$timestamp = $this->clock->now()->format( 'Y-m-d H:i:s' );
		$table     = $this->schema->schema_table();
		$query     = $this->database->prepare(
			"INSERT INTO {$table}
				(id, installed_version, target_version, state, retry_count, row_version, updated_at)
			VALUES (1, 0, 0, %s, 0, 0, %s)
			ON DUPLICATE KEY UPDATE id = id",
			array( 'uninitialized', $timestamp )
		);

		$this->database->execute( $query );
	}

	/**
	 * Whether both physical tables satisfy frozen column/index requirements.
	 */
	public function postconditions_met(): bool {
		foreach ( $this->schema->required_columns() as $table_name => $required_columns ) {
			if ( ! $this->database->table_exists( $table_name ) ) {
				return false;
			}

			$actual_columns = $this->database->table_columns( $table_name );

			foreach ( $required_columns as $column_name => $type_pattern ) {
				if (
					! isset( $actual_columns[ $column_name ] )
					|| 1 !== preg_match( $type_pattern, $actual_columns[ $column_name ] )
				) {
					return false;
				}
			}
		}

		foreach ( $this->schema->required_indexes() as $table_name => $required_indexes ) {
			$actual_indexes = $this->database->table_indexes( $table_name );

			foreach ( $required_indexes as $index_name => $required_index ) {
				if (
					! isset( $actual_indexes[ $index_name ] )
					|| $required_index !== $actual_indexes[ $index_name ]
				) {
					return false;
				}
			}
		}

		return true;
	}
}
