<?php
/**
 * Ordered schema migration contract.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Migrations;

defined( 'ABSPATH' ) || exit;

/**
 * Defines one idempotent, forward-only schema step.
 */
interface MigrationInterface {

	/**
	 * Stable lowercase migration identifier.
	 */
	public function id(): string;

	/**
	 * Required installed version before the step.
	 */
	public function from_version(): int;

	/**
	 * Installed version committed after verified success.
	 */
	public function to_version(): int;

	/**
	 * Apply the idempotent forward step.
	 */
	public function apply(): void;

	/**
	 * Whether this step's postconditions currently hold.
	 */
	public function postconditions_met(): bool;
}
