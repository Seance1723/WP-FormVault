<?php
/**
 * Verify the locked, prefixed, and staged runtime dependency set.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

$root            = dirname( __DIR__ );
$vendor_autoload = $root . '/vendor/autoload.php';
$scoped_autoload = $root . '/vendor-prefixed/autoload.php';
$scheduler_root  = $root . '/libraries/action-scheduler';

foreach ( array( $vendor_autoload, $scoped_autoload, $scheduler_root . '/action-scheduler.php' ) as $required_path ) {
	if ( ! file_exists( $required_path ) ) {
		fwrite( STDERR, "Missing generated dependency artifact: {$required_path}\n" );
		exit( 1 );
	}
}

require_once $vendor_autoload;

$expected_versions = array(
	'brianhenryie/strauss'         => '0.28.1',
	'maennchen/zipstream-php'      => '3.0.2',
	'phpoffice/phpspreadsheet'     => '5.8.1',
	'woocommerce/action-scheduler' => '3.9.3',
);

foreach ( $expected_versions as $package => $expected_version ) {
	$installed_version = Composer\InstalledVersions::getPrettyVersion( $package );

	if ( $expected_version !== $installed_version ) {
		fwrite(
			STDERR,
			"Unexpected locked version for {$package}: expected {$expected_version}, found "
			. ( $installed_version ?? 'unknown' )
			. ".\n"
		);
		exit( 1 );
	}
}

require_once __DIR__ . '/fixtures/dependency-conflict-stubs.php';

$scoped_loader = require $scoped_autoload;

$scoped_loader_class = 'WPFormVault\\Vendor\\Composer\\Autoload\\ClassLoader';

if ( ! $scoped_loader instanceof $scoped_loader_class ) {
	fwrite( STDERR, "The dependency autoloader did not return the expected prefixed class loader.\n" );
	exit( 1 );
}

$scoped_spreadsheet = 'WPFormVault\\Vendor\\PhpOffice\\PhpSpreadsheet\\Spreadsheet';
$scoped_zipstream   = 'WPFormVault\\Vendor\\ZipStream\\ZipStream';
$scoped_data_type   = 'WPFormVault\\Vendor\\PhpOffice\\PhpSpreadsheet\\Cell\\DataType';
$scoped_xlsx_writer = 'WPFormVault\\Vendor\\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx';
$scoped_complex     = 'WPFormVault\\Vendor\\Complex\\Complex';
$scoped_matrix      = 'WPFormVault\\Vendor\\Matrix\\Matrix';

if (
	! class_exists( $scoped_spreadsheet )
	|| ! class_exists( $scoped_zipstream )
	|| ! class_exists( $scoped_data_type )
	|| ! class_exists( $scoped_xlsx_writer )
	|| ! class_exists( $scoped_complex )
	|| ! class_exists( $scoped_matrix )
) {
	fwrite( STDERR, "Expected prefixed runtime classes were not autoloadable.\n" );
	exit( 1 );
}

if (
	! class_exists( 'PhpOffice\\PhpSpreadsheet\\Spreadsheet', false )
	|| ! class_exists( 'ZipStream\\ZipStream', false )
) {
	fwrite( STDERR, "Unprefixed conflict sentinels were unexpectedly replaced.\n" );
	exit( 1 );
}

if (
	false !== $scoped_loader->findFile( 'PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx' )
	|| false !== $scoped_loader->findFile( 'ZipStream\\ZipStream' )
) {
	fwrite( STDERR, "The prefixed autoloader exposes an unprefixed generic dependency.\n" );
	exit( 1 );
}

$complex_result = ( new $scoped_complex( 2, 3 ) )->reverse();
$matrix_result  = ( new $scoped_matrix( array( array( 1, 2 ), array( 3, 4 ) ) ) )->getRows( 1 );

if ( ! $complex_result instanceof $scoped_complex || ! $matrix_result instanceof $scoped_matrix ) {
	fwrite( STDERR, "Corrected Complex/Matrix return types failed runtime verification.\n" );
	exit( 1 );
}

$spreadsheet = new $scoped_spreadsheet();
$spreadsheet->getActiveSheet()->setCellValueExplicit(
	'A1',
	'WP FormVault dependency isolation smoke test',
	$scoped_data_type::TYPE_STRING
);

$temporary_base = tempnam( sys_get_temp_dir(), 'wpfv-dependency-' );

if ( false === $temporary_base ) {
	fwrite( STDERR, "Unable to allocate a temporary XLSX verification path.\n" );
	exit( 1 );
}

$temporary_xlsx = $temporary_base . '.xlsx';
unlink( $temporary_base );

try {
	$writer = new $scoped_xlsx_writer( $spreadsheet );
	$writer->save( $temporary_xlsx );

	if ( ! is_file( $temporary_xlsx ) || filesize( $temporary_xlsx ) < 1000 ) {
		fwrite( STDERR, "The prefixed PhpSpreadsheet writer did not produce a valid-sized XLSX file.\n" );
		exit( 1 );
	}

	$archive = new ZipArchive();

	if (
	true !== $archive->open( $temporary_xlsx )
	|| false === $archive->locateName( '[Content_Types].xml' )
	|| false === $archive->locateName( 'xl/worksheets/sheet1.xml' )
	) {
		fwrite( STDERR, "The prefixed PhpSpreadsheet/ZipStream output is not a valid XLSX archive.\n" );
		exit( 1 );
	}

	$archive->close();
} finally {
	$spreadsheet->disconnectWorksheets();

	if ( is_file( $temporary_xlsx ) ) {
		unlink( $temporary_xlsx );
	}
}

$scheduler_readme = file_get_contents( $scheduler_root . '/readme.txt' );
$scheduler_marker = json_decode(
	(string) file_get_contents( $scheduler_root . '/WP-FORMVAULT-PACKAGE.json' ),
	true
);

if (
	false === $scheduler_readme
	|| ! str_contains( $scheduler_readme, 'Stable tag: 3.9.3' )
	|| ! str_contains( $scheduler_readme, 'Requires at least: 6.5' )
	|| ! is_array( $scheduler_marker )
	|| '3.9.3' !== ( $scheduler_marker['version'] ?? null )
) {
	fwrite( STDERR, "The staged Action Scheduler artifact failed compatibility verification.\n" );
	exit( 1 );
}

$prefixed_php_files = new RegexIterator(
	new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $root . '/vendor-prefixed', FilesystemIterator::SKIP_DOTS )
	),
	'/\.php$/i'
);

foreach ( $prefixed_php_files as $file ) {
	$content = file_get_contents( $file->getPathname() );

	if ( false !== $content && preg_match( '/\bActionScheduler(?:_|\\\\)/', $content ) ) {
		fwrite( STDERR, "Action Scheduler symbols were found in the prefixed dependency tree.\n" );
		exit( 1 );
	}

	if (
	false !== $content
	&& preg_match(
		'/:\s*WPFormVault\\\\Vendor\\\\(?:Complex|Matrix|ZipStream)(?!\\\\)/',
		$content
	)
	) {
		fwrite( STDERR, "An ambiguous namespace/class-homonym return type remains in the generated tree.\n" );
		exit( 1 );
	}
}

echo "Locked dependency, namespace isolation, homonym types, XLSX/ZIP smoke, conflict, and Action Scheduler staging verification passed.\n";
