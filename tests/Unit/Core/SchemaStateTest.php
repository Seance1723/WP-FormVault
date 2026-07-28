<?php
/**
 * Schema state unit tests.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace WPFormVault\Tests\Unit\Core;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPFormVault\Core\Value\SchemaState;

/**
 * Verifies persisted schema state is validated before coordination decisions.
 */
final class SchemaStateTest extends TestCase {

	/**
	 * Valid wpdb string scalars hydrate an immutable state.
	 */
	public function test_hydrates_valid_database_scalars(): void {
		$state = SchemaState::from_row(
			array(
				'id'                => '1',
				'installed_version' => '2',
				'target_version'    => '3',
				'state'             => SchemaState::RUNNING,
				'current_migration' => 'add_indexes',
				'row_version'       => '7',
			)
		);

		self::assertSame( 2, $state->installed_version() );
		self::assertSame( 3, $state->target_version() );
		self::assertSame( SchemaState::RUNNING, $state->state() );
		self::assertSame( 'add_indexes', $state->current_migration() );
		self::assertSame( 7, $state->row_version() );
	}

	/**
	 * Unknown persisted states fail closed.
	 */
	public function test_rejects_unknown_state(): void {
		$this->expectException( InvalidArgumentException::class );

		SchemaState::from_row( $this->row( 'unknown' ) );
	}

	/**
	 * Unsigned BIGINT values beyond PHP's integer range cannot be truncated.
	 */
	public function test_rejects_integer_overflow(): void {
		$row                      = $this->row( SchemaState::READY );
		$row['installed_version'] = (string) PHP_INT_MAX . '0';

		$this->expectException( InvalidArgumentException::class );

		SchemaState::from_row( $row );
	}

	/**
	 * Build one otherwise-valid persisted row.
	 *
	 * @param string $state Persisted state.
	 * @return array<string, string|null>
	 */
	private function row( string $state ): array {
		return array(
			'id'                => '1',
			'installed_version' => '0',
			'target_version'    => '0',
			'state'             => $state,
			'current_migration' => null,
			'row_version'       => '0',
		);
	}
}
