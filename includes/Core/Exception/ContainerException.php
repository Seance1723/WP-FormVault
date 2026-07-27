<?php
/**
 * Service-container exception.
 *
 * @package WPFormVault
 */

namespace WPFormVault\Core\Exception;

use RuntimeException;

defined( 'ABSPATH' ) || exit;

/**
 * Represents an invalid definition or failed service resolution.
 */
final class ContainerException extends RuntimeException {
}
