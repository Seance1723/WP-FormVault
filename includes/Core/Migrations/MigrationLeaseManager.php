<?php
/**
 * Fenced migration lease manager.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Migrations;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use WPFormVault\Core\Contracts\ClockInterface;
use WPFormVault\Core\Contracts\RandomSourceInterface;
use WPFormVault\Core\Database\ControlPlaneSchema;
use WPFormVault\Core\Database\SchemaDatabaseInterface;
use WPFormVault\Core\Exception\SchemaException;
use WPFormVault\Core\Value\MigrationLease;

defined( 'ABSPATH' ) || exit;

/**
 * Acquires, heartbeats, verifies, and releases the schema_migration lease.
 */
final class MigrationLeaseManager {

	public const LOCK_KEY = 'schema_migration';

	/**
	 * Reviewed database boundary.
	 *
	 * @var SchemaDatabaseInterface
	 */
	private SchemaDatabaseInterface $database;

	/**
	 * Current-site control table names.
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
	 * Cryptographic random source.
	 *
	 * @var RandomSourceInterface
	 */
	private RandomSourceInterface $random;

	/**
	 * Lease duration.
	 *
	 * @var int
	 */
	private int $lease_seconds;

	/**
	 * Configure the current-site lease manager.
	 *
	 * @param SchemaDatabaseInterface $database      Reviewed database boundary.
	 * @param ControlPlaneSchema      $schema        Current-site table names.
	 * @param ClockInterface          $clock         UTC clock.
	 * @param RandomSourceInterface   $random        Cryptographic random source.
	 * @param int                     $lease_seconds Lease duration in seconds.
	 * @throws SchemaException When the configured duration is outside policy.
	 */
	public function __construct(
		SchemaDatabaseInterface $database,
		ControlPlaneSchema $schema,
		ClockInterface $clock,
		RandomSourceInterface $random,
		int $lease_seconds = 120
	) {
		if ( $lease_seconds < 30 || $lease_seconds > 3600 ) {
			throw new SchemaException( 'schema_lease_duration_invalid' );
		}

		$this->database      = $database;
		$this->schema        = $schema;
		$this->clock         = $clock;
		$this->random        = $random;
		$this->lease_seconds = $lease_seconds;
	}

	/**
	 * Acquire a new lease or atomically take over an expired lease.
	 *
	 * @param string $run_id Sanitized migration run identifier.
	 * @throws SchemaException When identifiers, randomness, metadata, or database state is invalid.
	 */
	public function acquire( string $run_id ): ?MigrationLease {
		if (
			1 !== preg_match(
				'/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',
				$run_id
			)
		) {
			throw new SchemaException( 'schema_run_id_invalid' );
		}

		$now         = $this->clock->now();
		$expires_at  = $this->expires_from( $now );
		$owner_token = $this->random->bytes( 32 );

		if ( 32 !== strlen( $owner_token ) ) {
			throw new SchemaException( 'schema_random_source_invalid' );
		}

		$owner_hash = hash( 'sha256', $owner_token );

		try {
			$metadata = wp_json_encode(
				array( 'run_id' => $run_id ),
				JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
			);
		} catch ( JsonException $exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- The previous exception is retained internally and is never rendered.
			throw new SchemaException( 'schema_lease_metadata_invalid', $exception );
		}

		$table = $this->schema->locks_table();
		$query = $this->database->prepare(
			"INSERT INTO {$table}
				(lock_key, owner_token_hash, owner_context, acquired_at, heartbeat_at, expires_at,
				fencing_token, metadata_json, created_at, updated_at)
			VALUES (%s, UNHEX(%s), %s, %s, %s, %s, 1, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				fencing_token = IF(expires_at <= VALUES(acquired_at), fencing_token + 1, fencing_token),
				owner_token_hash = IF(expires_at <= VALUES(acquired_at), VALUES(owner_token_hash), owner_token_hash),
				owner_context = IF(expires_at <= VALUES(acquired_at), VALUES(owner_context), owner_context),
				acquired_at = IF(expires_at <= VALUES(acquired_at), VALUES(acquired_at), acquired_at),
				heartbeat_at = IF(expires_at <= VALUES(acquired_at), VALUES(heartbeat_at), heartbeat_at),
				metadata_json = IF(expires_at <= VALUES(acquired_at), VALUES(metadata_json), metadata_json),
				updated_at = IF(expires_at <= VALUES(acquired_at), VALUES(updated_at), updated_at),
				expires_at = IF(expires_at <= VALUES(acquired_at), VALUES(expires_at), expires_at)",
			array(
				self::LOCK_KEY,
				$owner_hash,
				'migration',
				$this->format( $now ),
				$this->format( $now ),
				$this->format( $expires_at ),
				$metadata,
				$this->format( $now ),
				$this->format( $now ),
			)
		);

		$this->database->execute( $query );

		$row = $this->read();

		if ( null === $row || $owner_hash !== $row['owner_hash'] ) {
			return null;
		}

		return new MigrationLease( $owner_hash, $row['fencing_token'], $row['expires_at'] );
	}

	/**
	 * Whether an unexpired schema migration lease exists.
	 */
	public function active(): bool {
		$row = $this->read();

		return null !== $row && $row['expires_at'] > $this->clock->now();
	}

	/**
	 * Extend an owned lease while retaining its fence.
	 *
	 * @param MigrationLease $lease Owned fenced lease.
	 * @throws SchemaException When ownership has been lost.
	 */
	public function heartbeat( MigrationLease $lease ): MigrationLease {
		$now        = $this->clock->now();
		$expires_at = $this->expires_from( $now );
		$table      = $this->schema->locks_table();
		$query      = $this->database->prepare(
			"UPDATE {$table}
			SET heartbeat_at = %s, expires_at = %s, updated_at = %s
			WHERE lock_key = %s
				AND owner_token_hash = UNHEX(%s)
				AND fencing_token = %d
				AND expires_at > %s",
			array(
				$this->format( $now ),
				$this->format( $expires_at ),
				$this->format( $now ),
				self::LOCK_KEY,
				$lease->owner_hash(),
				$lease->fencing_token(),
				$this->format( $now ),
			)
		);

		if ( 1 !== $this->database->execute( $query ) ) {
			throw new SchemaException( 'schema_lease_lost' );
		}

		return new MigrationLease( $lease->owner_hash(), $lease->fencing_token(), $expires_at );
	}

	/**
	 * Release only the currently owned fence.
	 *
	 * @param MigrationLease $lease Owned fenced lease.
	 * @throws SchemaException When the owner hash or fence no longer matches.
	 */
	public function release( MigrationLease $lease ): void {
		$now   = $this->clock->now();
		$table = $this->schema->locks_table();
		$query = $this->database->prepare(
			"UPDATE {$table}
			SET owner_context = %s,
				heartbeat_at = %s,
				expires_at = %s,
				metadata_json = NULL,
				updated_at = %s
			WHERE lock_key = %s
				AND owner_token_hash = UNHEX(%s)
				AND fencing_token = %d",
			array(
				'released',
				$this->format( $now ),
				$this->format( $now ),
				$this->format( $now ),
				self::LOCK_KEY,
				$lease->owner_hash(),
				$lease->fencing_token(),
			)
		);

		if ( 1 !== $this->database->execute( $query ) ) {
			throw new SchemaException( 'schema_lease_release_conflict' );
		}
	}

	/**
	 * Read the current lease row.
	 *
	 * @return array{owner_hash:string, fencing_token:int, expires_at:DateTimeImmutable}|null
	 * @throws SchemaException When persisted lease state is malformed.
	 */
	private function read(): ?array {
		$table = $this->schema->locks_table();
		$query = $this->database->prepare(
			"SELECT LOWER(HEX(owner_token_hash)) AS owner_hash, fencing_token, expires_at
			FROM {$table}
			WHERE lock_key = %s",
			array( self::LOCK_KEY )
		);
		$row   = $this->database->fetch_row( $query );

		if ( null === $row ) {
			return null;
		}

		$owner_hash    = $row['owner_hash'] ?? null;
		$fencing_token = $row['fencing_token'] ?? null;
		$expires_at    = $row['expires_at'] ?? null;

		if (
			! is_string( $owner_hash )
			|| 1 !== preg_match( '/^[a-f0-9]{64}$/D', $owner_hash )
			|| ! is_numeric( $fencing_token )
			|| (int) $fencing_token < 1
			|| ! is_string( $expires_at )
		) {
			throw new SchemaException( 'schema_lease_state_invalid' );
		}

		$expiry = DateTimeImmutable::createFromFormat(
			'!Y-m-d H:i:s',
			$expires_at,
			new DateTimeZone( 'UTC' )
		);

		if ( false === $expiry ) {
			throw new SchemaException( 'schema_lease_state_invalid' );
		}

		return array(
			'owner_hash'    => $owner_hash,
			'fencing_token' => (int) $fencing_token,
			'expires_at'    => $expiry,
		);
	}

	/**
	 * Calculate one deterministic lease expiry.
	 *
	 * @param DateTimeImmutable $now Current UTC instant.
	 */
	private function expires_from( DateTimeImmutable $now ): DateTimeImmutable {
		return $now->add( new DateInterval( 'PT' . $this->lease_seconds . 'S' ) );
	}

	/**
	 * Format a UTC instant for portable DATETIME storage.
	 *
	 * @param DateTimeImmutable $instant UTC instant.
	 */
	private function format( DateTimeImmutable $instant ): string {
		return $instant->format( 'Y-m-d H:i:s' );
	}
}
