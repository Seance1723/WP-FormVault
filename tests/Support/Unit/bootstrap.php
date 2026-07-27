<?php
/**
 * PHPUnit bootstrap for tests that do not load WordPress.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/wordpress-not-loaded/' );

require dirname( __DIR__, 3 ) . '/vendor/autoload.php';
