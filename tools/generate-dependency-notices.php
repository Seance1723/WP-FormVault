<?php
/**
 * Generate deterministic production dependency notices from composer.lock.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

$root      = dirname( __DIR__ );
$lock_path = $root . '/composer.lock';
$target    = $root . '/DEPENDENCY-LICENSES.md';
$lock      = json_decode( (string) file_get_contents( $lock_path ), true );

if ( ! is_array( $lock ) || ! isset( $lock['packages'] ) || ! is_array( $lock['packages'] ) ) {
	fwrite( STDERR, "Unable to read runtime packages from composer.lock.\n" );
	exit( 1 );
}

$packages = $lock['packages'];

usort(
	$packages,
	static fn ( array $left, array $right ): int => strcmp(
		(string) ( $left['name'] ?? '' ),
		(string) ( $right['name'] ?? '' )
	)
);

$lines = array(
	'# WP FormVault Runtime Dependency Notices',
	'',
	'This file is generated deterministically from the runtime `packages` section of `composer.lock` by `tools/generate-dependency-notices.php`. Do not edit it manually.',
	'',
	'The production package must include this notice and the corresponding license files copied into `vendor-prefixed/` or `libraries/action-scheduler/`.',
	'',
	'| Package | Locked version | License | Source |',
	'|---|---:|---|---|',
);

foreach ( $packages as $package ) {
	$name     = (string) ( $package['name'] ?? 'unknown' );
	$version  = (string) ( $package['version'] ?? 'unknown' );
	$licenses = isset( $package['license'] ) && is_array( $package['license'] )
		? implode( ', ', $package['license'] )
		: 'Not declared';
	$source   = (string) ( $package['source']['url'] ?? '' );

	if ( '' !== $source ) {
		$source = preg_replace( '/\.git$/', '', $source ) ?? $source;
		$source = "[upstream]({$source})";
	} else {
		$source = 'Not declared';
	}

	$lines[] = "| `{$name}` | `{$version}` | {$licenses} | {$source} |";
}

$contents = implode( PHP_EOL, $lines ) . PHP_EOL;

if ( false === file_put_contents( $target, $contents ) ) {
	fwrite( STDERR, "Unable to write dependency notices.\n" );
	exit( 1 );
}

echo 'Generated runtime dependency notices for ' . count( $packages ) . " packages.\n";
