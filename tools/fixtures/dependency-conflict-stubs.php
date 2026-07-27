<?php
/**
 * Deliberately conflicting unprefixed symbols for dependency-isolation tests.
 *
 * @package WPFormVault
 */

declare(strict_types=1);

namespace PhpOffice\PhpSpreadsheet;

final class Spreadsheet {
}

namespace ZipStream;

final class ZipStream {
}
