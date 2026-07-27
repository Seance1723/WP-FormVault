<?php
/**
 * Correct reviewed Strauss 0.28.1 return-type ambiguity for the current lock.
 *
 * Strauss interprets short class names that equal their namespace root as the
 * namespace itself when rewriting return types. The current locked dependency
 * set has three such class names. This patch is deliberately count-locked and
 * fails when an upstream or prefixer update changes the generated surface.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

$root = dirname( __DIR__ ) . '/vendor-prefixed';

if ( ! is_dir( $root ) ) {
	fwrite( STDERR, "The prefixed dependency tree does not exist.\n" );
	exit( 1 );
}

$corrections = array(
	'Complex'   => array(
		'expected'    => 42,
		'replacement' => '\\WPFormVault\\Vendor\\Complex\\Complex',
	),
	'Matrix'    => array(
		'expected'    => 21,
		'replacement' => '\\WPFormVault\\Vendor\\Matrix\\Matrix',
	),
	'ZipStream' => array(
		'expected'    => 4,
		'replacement' => '\\WPFormVault\\Vendor\\ZipStream\\ZipStream',
	),
);

$actual_counts = array_fill_keys( array_keys( $corrections ), 0 );

$php_files = new RegexIterator(
	new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS )
	),
	'/\.php$/i'
);

foreach ( $php_files as $file ) {
	$path    = $file->getPathname();
	$content = file_get_contents( $path );

	if ( false === $content ) {
		fwrite( STDERR, "Unable to read generated dependency file: {$path}\n" );
		exit( 1 );
	}

	$updated = $content;

	foreach ( $corrections as $short_name => $correction ) {
		$pattern = '/(:\s*)WPFormVault\\\\Vendor\\\\'
			. preg_quote( $short_name, '/' )
			. '(?!\\\\)/';

		$updated = preg_replace(
			$pattern,
			'$1' . str_replace( '\\', '\\\\', $correction['replacement'] ),
			$updated,
			-1,
			$count
		);

		if ( null === $updated ) {
			fwrite( STDERR, "Unable to patch generated {$short_name} return types.\n" );
			exit( 1 );
		}

		$actual_counts[ $short_name ] += $count;
	}

	if ( $updated !== $content && false === file_put_contents( $path, $updated ) ) {
		fwrite( STDERR, "Unable to write corrected generated dependency file: {$path}\n" );
		exit( 1 );
	}
}

foreach ( $corrections as $short_name => $correction ) {
	if ( $correction['expected'] !== $actual_counts[ $short_name ] ) {
		fwrite(
			STDERR,
			"Unexpected {$short_name} correction count: expected {$correction['expected']}, "
			. "found {$actual_counts[$short_name]}.\n"
		);
		exit( 1 );
	}
}

echo 'Corrected reviewed Strauss namespace/class-homonym return types: '
	. implode(
		', ',
		array_map(
			static fn ( string $name, int $count ): string => "{$name}={$count}",
			array_keys( $actual_counts ),
			array_values( $actual_counts )
		)
	)
	. ".\n";
