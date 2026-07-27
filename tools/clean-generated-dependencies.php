<?php
/**
 * Remove only WP FormVault's generated dependency directories before rebuild.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

$root = realpath( dirname( __DIR__ ) );

if ( false === $root ) {
	fwrite( STDERR, "Unable to resolve repository root.\n" );
	exit( 1 );
}

$allowed_targets = array(
	$root . DIRECTORY_SEPARATOR . 'vendor-prefixed',
	$root . DIRECTORY_SEPARATOR . 'libraries' . DIRECTORY_SEPARATOR . 'action-scheduler',
);

/**
 * Remove a generated tree after validating its exact location.
 *
 * @param string   $path            Target path.
 * @param string   $root            Repository root.
 * @param string[] $allowed_targets Exact allowed targets.
 * @return void
 */
function wpfv_remove_generated_tree( string $path, string $root, array $allowed_targets ): void {
	if ( ! in_array( $path, $allowed_targets, true ) ) {
		fwrite( STDERR, "Refusing to remove an unapproved path: {$path}\n" );
		exit( 1 );
	}

	$normalized_root = rtrim( str_replace( '\\', '/', $root ), '/' ) . '/';
	$normalized_path = str_replace( '\\', '/', $path );

	if ( ! str_starts_with( $normalized_path, $normalized_root ) ) {
		fwrite( STDERR, "Refusing to remove a path outside the repository: {$path}\n" );
		exit( 1 );
	}

	if ( ! file_exists( $path ) && ! is_link( $path ) ) {
		return;
	}

	if ( is_link( $path ) || is_file( $path ) ) {
		if ( ! unlink( $path ) ) {
			fwrite( STDERR, "Unable to remove generated file: {$path}\n" );
			exit( 1 );
		}

		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $iterator as $item ) {
		$item_path = $item->getPathname();
		$removed   = $item->isLink() || $item->isFile()
			? unlink( $item_path )
			: rmdir( $item_path );

		if ( ! $removed ) {
			fwrite( STDERR, "Unable to remove generated path: {$item_path}\n" );
			exit( 1 );
		}
	}

	if ( ! rmdir( $path ) ) {
		fwrite( STDERR, "Unable to remove generated directory: {$path}\n" );
		exit( 1 );
	}
}

foreach ( $allowed_targets as $target ) {
	wpfv_remove_generated_tree( $target, $root, $allowed_targets );
}

echo "Generated dependency directories are clean.\n";
