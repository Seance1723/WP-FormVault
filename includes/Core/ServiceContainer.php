<?php
/**
 * Explicit WP FormVault service container.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core;

use Closure;
use Throwable;
use WPFormVault\Core\Exception\ContainerException;

defined( 'ABSPATH' ) || exit;

/**
 * Stores explicit values and factories for one request and site context.
 */
final class ServiceContainer {

	/**
	 * WordPress site/blog ID represented by this graph.
	 *
	 * @var int
	 */
	private int $site_id;

	/**
	 * Registered values and service factories.
	 *
	 * @var array<string, array{kind:string, value:mixed, shared:bool}>
	 */
	private array $definitions = array();

	/**
	 * Public contract aliases mapped to service identifiers.
	 *
	 * @var array<string, string>
	 */
	private array $aliases = array();

	/**
	 * Cached shared service values.
	 *
	 * @var array<string, mixed>
	 */
	private array $resolved = array();

	/**
	 * Service identifiers currently being resolved.
	 *
	 * @var array<string, true>
	 */
	private array $resolving = array();

	/**
	 * Whether definition registration has ended.
	 *
	 * @var bool
	 */
	private bool $frozen = false;

	/**
	 * Create an isolated service graph for one site.
	 *
	 * @param int $site_id Current WordPress site/blog ID.
	 * @throws ContainerException When the site ID is not positive.
	 */
	public function __construct( int $site_id ) {
		if ( $site_id < 1 ) {
			throw new ContainerException( 'The service container requires a positive site ID.' );
		}

		$this->site_id = $site_id;
	}

	/**
	 * Current site/blog ID for this graph.
	 */
	public function site_id(): int {
		return $this->site_id;
	}

	/**
	 * Register an already-created immutable value or service.
	 *
	 * @param string $id    Service identifier.
	 * @param mixed  $value Service value.
	 */
	public function set( string $id, mixed $value ): void {
		$this->assert_mutable();
		$this->assert_available_id( $id );

		$this->definitions[ $id ] = array(
			'kind'   => 'value',
			'value'  => $value,
			'shared' => true,
		);
	}

	/**
	 * Register a lazy service shared within this request/site graph.
	 *
	 * @param string  $id      Service identifier.
	 * @param Closure $factory Explicit service factory.
	 */
	public function share( string $id, Closure $factory ): void {
		$this->register_factory( $id, $factory, true );
	}

	/**
	 * Register a factory that creates a new operation object on every resolve.
	 *
	 * @param string  $id      Service identifier.
	 * @param Closure $factory Explicit service factory.
	 */
	public function transient( string $id, Closure $factory ): void {
		$this->register_factory( $id, $factory, false );
	}

	/**
	 * Map a public contract identifier to one reviewed service definition.
	 *
	 * @param string $alias  Public alias/contract.
	 * @param string $target Target service identifier.
	 * @throws ContainerException When either identifier is invalid or unavailable.
	 */
	public function alias( string $alias, string $target ): void {
		$this->assert_mutable();
		$this->assert_available_id( $alias );
		$this->assert_valid_id( $target );

		if ( $alias === $target ) {
			throw new ContainerException( "Service alias {$alias} cannot target itself." );
		}

		$this->aliases[ $alias ] = $target;
	}

	/**
	 * Whether an identifier resolves to a definition.
	 *
	 * @param string $id Service identifier.
	 */
	public function has( string $id ): bool {
		$this->assert_valid_id( $id );

		try {
			$resolved_id = $this->resolve_alias( $id );
		} catch ( ContainerException ) {
			return false;
		}

		return isset( $this->definitions[ $resolved_id ] );
	}

	/**
	 * Resolve a service/value.
	 *
	 * General-purpose resolution is restricted to the composition root.
	 *
	 * @param string $id Service identifier.
	 * @return mixed
	 * @throws ContainerException When the identifier cannot be resolved safely.
	 */
	public function get( string $id ): mixed {
		$this->assert_valid_id( $id );

		$resolved_id = $this->resolve_alias( $id );

		if ( ! isset( $this->definitions[ $resolved_id ] ) ) {
			throw new ContainerException( "Service is not defined: {$id}." );
		}

		$definition = $this->definitions[ $resolved_id ];

		if ( $definition['shared'] && array_key_exists( $resolved_id, $this->resolved ) ) {
			$value = $this->resolved[ $resolved_id ];
			$this->assert_expected_type( $id, $value );
			return $value;
		}

		if ( isset( $this->resolving[ $resolved_id ] ) ) {
			$chain   = array_keys( $this->resolving );
			$chain[] = $resolved_id;

			throw new ContainerException( 'Circular service resolution: ' . implode( ' -> ', $chain ) . '.' );
		}

		$this->resolving[ $resolved_id ] = true;

		try {
			$value = 'factory' === $definition['kind']
				? ( $definition['value'] )( $this )
				: $definition['value'];

			$this->assert_expected_type( $resolved_id, $value );
			$this->assert_expected_type( $id, $value );

			if ( $definition['shared'] ) {
				$this->resolved[ $resolved_id ] = $value;
			}

			return $value;
		} catch ( ContainerException $exception ) {
			throw $exception;
		} catch ( Throwable $throwable ) {
			throw new ContainerException(
				"Service factory failed for {$resolved_id}.",
				0,
				$throwable
			);
		} finally {
			unset( $this->resolving[ $resolved_id ] );
		}
	}

	/**
	 * Validate aliases and prevent any later definition changes.
	 *
	 * @throws ContainerException When an alias is circular or targets a missing definition.
	 */
	public function freeze(): void {
		if ( $this->frozen ) {
			return;
		}

		foreach ( array_keys( $this->aliases ) as $alias ) {
			$target = $this->resolve_alias( $alias );

			if ( ! isset( $this->definitions[ $target ] ) ) {
				throw new ContainerException( "Service alias {$alias} targets missing service {$target}." );
			}
		}

		$this->frozen = true;
	}

	/**
	 * Whether definition registration has ended.
	 */
	public function is_frozen(): bool {
		return $this->frozen;
	}

	/**
	 * Register a service factory.
	 *
	 * @param string  $id      Service identifier.
	 * @param Closure $factory Explicit factory.
	 * @param bool    $shared  Whether to cache one resolved value.
	 * @throws ContainerException When the identifier is invalid or unavailable.
	 */
	private function register_factory( string $id, Closure $factory, bool $shared ): void {
		$this->assert_mutable();
		$this->assert_available_id( $id );

		$this->definitions[ $id ] = array(
			'kind'   => 'factory',
			'value'  => $factory,
			'shared' => $shared,
		);
	}

	/**
	 * Resolve an alias chain and detect alias cycles.
	 *
	 * @param string $id Service or alias identifier.
	 * @throws ContainerException When an alias cycle is found.
	 */
	private function resolve_alias( string $id ): string {
		$current = $id;
		$seen    = array();

		while ( isset( $this->aliases[ $current ] ) ) {
			if ( isset( $seen[ $current ] ) ) {
				$chain   = array_keys( $seen );
				$chain[] = $current;

				throw new ContainerException( 'Circular service alias: ' . implode( ' -> ', $chain ) . '.' );
			}

			$seen[ $current ] = true;
			$current          = $this->aliases[ $current ];
		}

		return $current;
	}

	/**
	 * Reject definition changes after freeze.
	 *
	 * @throws ContainerException When the graph is frozen.
	 */
	private function assert_mutable(): void {
		if ( $this->frozen ) {
			throw new ContainerException( 'The service container is frozen.' );
		}
	}

	/**
	 * Validate an identifier and ensure it is unused.
	 *
	 * @param string $id Service identifier.
	 * @throws ContainerException When the identifier is invalid or already registered.
	 */
	private function assert_available_id( string $id ): void {
		$this->assert_valid_id( $id );

		if ( isset( $this->definitions[ $id ] ) || isset( $this->aliases[ $id ] ) ) {
			throw new ContainerException( "Duplicate service definition: {$id}." );
		}
	}

	/**
	 * Validate a service identifier.
	 *
	 * @param string $id Service identifier.
	 * @throws ContainerException When the identifier is empty, padded, or contains control characters.
	 */
	private function assert_valid_id( string $id ): void {
		if (
			'' === $id
			|| trim( $id ) !== $id
			|| 1 === preg_match( '/[\x00-\x1F\x7F]/', $id )
		) {
			throw new ContainerException( 'Service identifiers must be non-empty text without surrounding whitespace or control characters.' );
		}
	}

	/**
	 * Enforce class/interface identifiers when the identifier names a type.
	 *
	 * @param string $id    Requested identifier.
	 * @param mixed  $value Resolved value.
	 * @throws ContainerException When a typed identifier receives an incompatible value.
	 */
	private function assert_expected_type( string $id, mixed $value ): void {
		if (
			( class_exists( $id ) || interface_exists( $id ) )
			&& ! $value instanceof $id
		) {
			throw new ContainerException( "Resolved service does not satisfy {$id}." );
		}
	}
}
