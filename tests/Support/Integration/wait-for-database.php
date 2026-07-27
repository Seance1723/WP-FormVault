<?php
/**
 * Wait for the isolated WordPress test database.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

if ( ! extension_loaded( 'mysqli' ) ) {
	fwrite( STDERR, "The mysqli extension is required for WordPress integration tests.\n" );
	exit( 1 );
}

$host     = getenv( 'WPFV_TEST_DB_HOST' ) ? getenv( 'WPFV_TEST_DB_HOST' ) : '127.0.0.1';
$user     = getenv( 'WPFV_TEST_DB_USER' ) ? getenv( 'WPFV_TEST_DB_USER' ) : 'root';
$password = getenv( 'WPFV_TEST_DB_PASSWORD' ) ? getenv( 'WPFV_TEST_DB_PASSWORD' ) : '';
$database = getenv( 'WPFV_TEST_DB_NAME' ) ? getenv( 'WPFV_TEST_DB_NAME' ) : 'wordpress_test';
$attempts = 60;

mysqli_report( MYSQLI_REPORT_OFF );

for ( $attempt = 1; $attempt <= $attempts; ++$attempt ) {
	$connection = new mysqli( $host, $user, $password, $database );

	if ( 0 === $connection->connect_errno ) {
		$result  = $connection->query( 'SELECT VERSION() AS database_version' );
		$version = $result instanceof mysqli_result
			? (string) ( $result->fetch_assoc()['database_version'] ?? 'unknown' )
			: 'unknown';

		$connection->close();
		echo "WordPress test database is ready: {$version}\n";
		exit( 0 );
	}

	$connection->close();
	usleep( 1000000 );
}

fwrite( STDERR, "The isolated WordPress test database did not become ready within 60 seconds.\n" );
exit( 1 );
