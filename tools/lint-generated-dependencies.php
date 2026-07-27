<?php
/**
 * Syntax-lint every generated production dependency PHP file.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

$root        = dirname( __DIR__ );
$directories = array(
	$root . '/vendor-prefixed',
	$root . '/libraries/action-scheduler',
);
$php_files   = array();

foreach ( $directories as $directory ) {
	if ( ! is_dir( $directory ) ) {
		fwrite( STDERR, "Generated dependency directory is missing: {$directory}\n" );
		exit( 1 );
	}

	$iterator = new RegexIterator(
		new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS )
		),
		'/\.php$/i'
	);

	foreach ( $iterator as $file ) {
		$php_files[] = $file->getPathname();
	}
}

sort( $php_files, SORT_STRING );

foreach ( $php_files as $path ) {
	$process = proc_open(
		array( PHP_BINARY, '-l', $path ),
		array(
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes
	);

	if ( ! is_resource( $process ) ) {
		fwrite( STDERR, "Unable to start PHP syntax check for: {$path}\n" );
		exit( 1 );
	}

	$standard_output = stream_get_contents( $pipes[1] );
	$error_output    = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );

	$exit_code = proc_close( $process );

	if ( 0 !== $exit_code ) {
		fwrite(
			STDERR,
			"Generated dependency syntax check failed: {$path}\n"
			. $standard_output
			. $error_output
		);
		exit( 1 );
	}
}

echo 'Generated dependency syntax verification passed: ' . count( $php_files ) . " PHP files.\n";
