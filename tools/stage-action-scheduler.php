<?php
/**
 * Stage the locked, intentionally unprefixed Action Scheduler runtime.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

$root            = dirname( __DIR__ );
$vendor_autoload = $root . '/vendor/autoload.php';

if ( ! is_file( $vendor_autoload ) ) {
	fwrite( STDERR, "Composer dependencies are not installed.\n" );
	exit( 1 );
}

require_once $vendor_autoload;

$package = 'woocommerce/action-scheduler';
$version = Composer\InstalledVersions::getPrettyVersion( $package );
$source  = Composer\InstalledVersions::getInstallPath( $package );
$target  = $root . '/libraries/action-scheduler';

if ( '3.9.3' !== $version ) {
	fwrite( STDERR, 'Expected Action Scheduler 3.9.3; found ' . ( $version ?? 'unknown' ) . ".\n" );
	exit( 1 );
}

if ( null === $source || ! is_dir( $source ) || ! is_file( $source . '/action-scheduler.php' ) ) {
	fwrite( STDERR, "Unable to locate the installed Action Scheduler runtime.\n" );
	exit( 1 );
}

if ( file_exists( $target ) ) {
	fwrite( STDERR, "Action Scheduler target must be clean before staging.\n" );
	exit( 1 );
}

$excluded_names = array(
	'.git',
	'.github',
	'node_modules',
	'tests',
);

$iterator = new RecursiveIteratorIterator(
	new RecursiveCallbackFilterIterator(
		new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
		static function ( SplFileInfo $item ) use ( $excluded_names ): bool {
			return ! in_array( $item->getFilename(), $excluded_names, true );
		}
	),
	RecursiveIteratorIterator::SELF_FIRST
);

foreach ( $iterator as $item ) {
	$relative_path = substr( $item->getPathname(), strlen( $source ) + 1 );
	$target_path   = $target . DIRECTORY_SEPARATOR . $relative_path;

	if ( $item->isDir() ) {
		if ( ! is_dir( $target_path ) && ! mkdir( $target_path, 0777, true ) && ! is_dir( $target_path ) ) {
			fwrite( STDERR, "Unable to create staged directory: {$target_path}\n" );
			exit( 1 );
		}

		continue;
	}

	$target_parent = dirname( $target_path );

	if ( ! is_dir( $target_parent ) && ! mkdir( $target_parent, 0777, true ) && ! is_dir( $target_parent ) ) {
		fwrite( STDERR, "Unable to create staged directory: {$target_parent}\n" );
		exit( 1 );
	}

	if ( ! copy( $item->getPathname(), $target_path ) ) {
		fwrite( STDERR, "Unable to stage Action Scheduler file: {$relative_path}\n" );
		exit( 1 );
	}
}

$readme = file_get_contents( $target . '/readme.txt' );

if (
	false === $readme
	|| ! str_contains( $readme, 'Stable tag: 3.9.3' )
	|| ! str_contains( $readme, 'Requires at least: 6.5' )
) {
	fwrite( STDERR, "Staged Action Scheduler metadata does not match the selected compatibility profile.\n" );
	exit( 1 );
}

$marker = array(
	'package' => $package,
	'version' => $version,
	'profile' => 'wordpress-6.5',
);

if (
	false === file_put_contents(
		$target . '/WP-FORMVAULT-PACKAGE.json',
		json_encode( $marker, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL
	)
) {
	fwrite( STDERR, "Unable to write the staged dependency marker.\n" );
	exit( 1 );
}

echo "Action Scheduler 3.9.3 staged without namespace rewriting.\n";
