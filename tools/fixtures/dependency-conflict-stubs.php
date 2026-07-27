<?php
/**
 * Deliberately conflicting unprefixed symbols for dependency-isolation tests.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace PhpOffice\PhpSpreadsheet;

/**
 * Deliberately conflicting PhpSpreadsheet symbol.
 */
final class Spreadsheet {
}

namespace ZipStream;

/**
 * Deliberately conflicting ZipStream symbol.
 */
final class ZipStream {
}
