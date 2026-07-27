<?php
/**
 * Verify the PHP platform used to resolve and build runtime dependencies.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

$minimum_php = '8.1.0';

if ( version_compare( PHP_VERSION, $minimum_php, '<' ) ) {
	fwrite( STDERR, "PHP {$minimum_php} or newer is required; found " . PHP_VERSION . ".\n" );
	exit( 1 );
}

if ( 8 !== PHP_INT_SIZE ) {
	fwrite( STDERR, 'A 64-bit PHP runtime is required.' . PHP_EOL );
	exit( 1 );
}

$required_extensions = array(
	'ctype',
	'dom',
	'fileinfo',
	'filter',
	'gd',
	'iconv',
	'libxml',
	'mbstring',
	'simplexml',
	'xml',
	'xmlreader',
	'xmlwriter',
	'zip',
	'zlib',
);

$missing_extensions = array_values(
	array_filter(
		$required_extensions,
		static fn ( string $extension ): bool => ! extension_loaded( $extension )
	)
);

if ( array() !== $missing_extensions ) {
	fwrite(
		STDERR,
		'Missing required PHP extensions: ' . implode( ', ', $missing_extensions ) . PHP_EOL
	);
	exit( 1 );
}

echo 'Dependency platform verification passed: PHP ' . PHP_VERSION . ', 64-bit, required extensions loaded.' . PHP_EOL;
