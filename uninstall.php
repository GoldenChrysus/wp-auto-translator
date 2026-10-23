<?php
/**
 * Removes plugin options and the translation map.
 *
 * Translated posts are left in place. Deleting them on uninstall would destroy content.
 *
 * @package WpAutoTranslator
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/src/Autoloader.php';
WpAutoTranslator\Autoloader::register();
WpAutoTranslator\Uninstaller::run();
