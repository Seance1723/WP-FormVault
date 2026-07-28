<?php
/**
 * Migration registry unit tests.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace WPFormVault\Tests\Unit\Core;

use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WPFormVault\Core\Migrations\MigrationInterface;
use WPFormVault\Core\Migrations\MigrationRegistry;

/**
 * Verifies ordering and chain-integrity rules for numbered migrations.
 */
final class MigrationRegistryTest extends TestCase {

	/**
	 * An empty chain represents the control-plane-only target zero.
	 */
	public function test_empty_registry_targets_version_zero(): void {
		$registry = new MigrationRegistry();

		self::assertSame( 0, $registry->target_version() );
		self::assertNull( $registry->migration_from( 0 ) );
		self::assertTrue( $registry->postconditions_met_through( 0 ) );
	}

	/**
	 * Input order cannot alter the contiguous migration order.
	 */
	public function test_registry_orders_a_contiguous_chain(): void {
		$first    = $this->migration( 'create_records', 0, 1, true );
		$second   = $this->migration( 'add_record_indexes', 1, 2, true );
		$registry = new MigrationRegistry( array( $second, $first ) );

		self::assertSame( 2, $registry->target_version() );
		self::assertSame( $first, $registry->migration_from( 0 ) );
		self::assertSame( $second, $registry->migration_from( 1 ) );
		self::assertTrue( $registry->postconditions_met_through( 2 ) );
	}

	/**
	 * A missing predecessor fails construction before database work can begin.
	 */
	public function test_registry_rejects_a_version_gap(): void {
		$this->expectException( InvalidArgumentException::class );

		new MigrationRegistry(
			array( $this->migration( 'starts_too_late', 1, 2, true ) )
		);
	}

	/**
	 * A false migration postcondition prevents readiness.
	 */
	public function test_postcondition_failure_is_reported(): void {
		$registry = new MigrationRegistry(
			array( $this->migration( 'unverified_step', 0, 1, false ) )
		);

		self::assertFalse( $registry->postconditions_met_through( 1 ) );
	}

	/**
	 * Build one deterministic migration mock.
	 *
	 * @param string $id                 Stable migration identifier.
	 * @param int    $from               Source version.
	 * @param int    $to                 Destination version.
	 * @param bool   $postconditions_met Postcondition outcome.
	 * @return MigrationInterface&MockObject
	 */
	private function migration(
		string $id,
		int $from,
		int $to,
		bool $postconditions_met
	): MigrationInterface {
		$migration = $this->createMock( MigrationInterface::class );
		$migration->method( 'id' )->willReturn( $id );
		$migration->method( 'from_version' )->willReturn( $from );
		$migration->method( 'to_version' )->willReturn( $to );
		$migration->method( 'postconditions_met' )->willReturn( $postconditions_met );

		return $migration;
	}
}
