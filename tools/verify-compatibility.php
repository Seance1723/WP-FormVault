<?php
/**
 * Verify that the selected platform and dependency compatibility profile is
 * synchronized across active project sources.
 *
 * Run from the repository root:
 * php tools/verify-compatibility.php
 *
 * @package WPFormVault
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

$files = array(
	'plan'      => $root . '/IMPLEMENTATION_PLAN.md',
	'bootstrap' => $root . '/wp-formvault.php',
	'policy'    => $root . '/docs/architecture/dependency-policy.md',
	'memory'    => $root . '/MEMORY.md',
	'tasks'     => $root . '/TASKS.md',
);

$contents = array();

foreach ( $files as $key => $path ) {
	$content = file_get_contents( $path );

	if ( false === $content ) {
		fwrite( STDERR, "Unable to read compatibility source: {$path}\n" );
		exit( 1 );
	}

	$contents[ $key ] = $content;
}

$required_fragments = array(
	'plan'      => array(
		'**Target platform:** WordPress 6.5+ / PHP 8.1+ (64-bit) / MySQL 5.7+ or MariaDB 10.4+',
	),
	'bootstrap' => array(
		' * Requires at least: 6.5',
		"define( 'WPFV_MINIMUM_WORDPRESS_VERSION', '6.5' );",
		' * Requires PHP:      8.1',
		"define( 'WPFV_MINIMUM_PHP_VERSION', '8.1' );",
	),
	'policy'    => array(
		'woocommerce/action-scheduler: ~3.9.3',
		'WordPress minimum: 6.5',
		'PHP minimum: 8.1 on 64-bit architecture',
		'Action Scheduler 4.0.0 is the current upstream release',
	),
	'memory'    => array(
		'| WordPress | 6.5 | Required/frozen by user decision on 2026-07-27 |',
		'| Action Scheduler | 3.9.3 | Required dependency baseline |',
		'Version 3.9.3 remains intentionally pinned as the latest line compatible with the user-selected WordPress 6.5 floor.',
	),
	'tasks'     => array(
		'WordPress 6.5+, Action Scheduler 3.9.3, PHP 8.1+ on 64-bit',
	),
);

foreach ( $required_fragments as $file_key => $fragments ) {
	foreach ( $fragments as $fragment ) {
		if ( ! str_contains( $contents[ $file_key ], $fragment ) ) {
			fwrite( STDERR, "Compatibility source '{$file_key}' is missing: {$fragment}\n" );
			exit( 1 );
		}
	}
}

if ( str_contains( $contents['plan'], 'WordPress 6.2+' ) ) {
	fwrite( STDERR, "The active implementation plan still advertises WordPress 6.2+.\n" );
	exit( 1 );
}

if ( preg_match( '/Requires at least:\s*6\.2/', $contents['bootstrap'] ) ) {
	fwrite( STDERR, "The plugin header still advertises WordPress 6.2.\n" );
	exit( 1 );
}

echo "WP FormVault compatibility verification passed.\n";
