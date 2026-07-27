<?php
/**
 * Gate result unit tests.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace WPFormVault\Tests\Unit\Core;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WPFormVault\Core\Value\GateResult;

/**
 * Verifies the immutable gate-result contract without loading WordPress.
 */
final class GateResultTest extends TestCase {

	/**
	 * A passing result has only the stable success identity.
	 */
	public function test_pass_result_has_stable_identity(): void {
		$result = GateResult::pass();

		self::assertTrue( $result->passed() );
		self::assertSame( 'ok', $result->code() );
		self::assertSame( '', $result->message() );
	}

	/**
	 * Failure messages cannot contain a second log or HTML line.
	 */
	public function test_failure_rejects_multiline_message(): void {
		$this->expectException( InvalidArgumentException::class );

		GateResult::failure( 'unsafe_message', "Safe first line\nunsafe second line" );
	}
}
