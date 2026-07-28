<?php
/**
 * Control-plane schema unit tests.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace WPFormVault\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use WPFormVault\Core\Database\ControlPlaneSchema;
use WPFormVault\Core\Exception\SchemaException;

/**
 * Verifies frozen table naming and schema definitions without loading WordPress.
 */
final class ControlPlaneSchemaTest extends TestCase {

	/**
	 * Current-site prefixes are retained in both physical table names.
	 */
	public function test_resolves_current_site_table_names(): void {
		$schema = new ControlPlaneSchema( 'tenant_42_', 'wpfv_' );

		self::assertSame( 'tenant_42_wpfv_schema_version', $schema->schema_table() );
		self::assertSame( 'tenant_42_wpfv_locks', $schema->locks_table() );
	}

	/**
	 * A hard-coded default WordPress prefix cannot replace the current site prefix.
	 */
	public function test_rejects_unsafe_site_prefix(): void {
		$this->expectException( SchemaException::class );

		new ControlPlaneSchema( 'tenant-42_', 'wpfv_' );
	}

	/**
	 * The product suffix prefix is a frozen invariant.
	 */
	public function test_rejects_non_product_prefix(): void {
		$this->expectException( SchemaException::class );

		new ControlPlaneSchema( 'tenant_42_', 'other_' );
	}

	/**
	 * Both dbDelta statements use only the resolved per-site names.
	 */
	public function test_create_statements_cover_both_control_tables(): void {
		$schema     = new ControlPlaneSchema( 'tenant_42_', 'wpfv_' );
		$statements = $schema->create_statements( 'DEFAULT CHARACTER SET utf8mb4' );

		self::assertCount( 2, $statements );
		self::assertStringContainsString( 'CREATE TABLE tenant_42_wpfv_schema_version', $statements[0] );
		self::assertStringContainsString( 'CREATE TABLE tenant_42_wpfv_locks', $statements[1] );
		self::assertStringContainsString( 'owner_token_hash binary(32) NOT NULL', $statements[1] );
		self::assertStringNotContainsString( 'CREATE TABLE wp_', implode( "\n", $statements ) );
	}
}
