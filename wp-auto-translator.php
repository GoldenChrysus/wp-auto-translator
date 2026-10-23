<?php
/**
 * Plugin Name:       WP Auto Translator
 * Description:       Translates posts and pages with OpenAI and lets the REST API filter by locale.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      8.1
 * Author:            WP Auto Translator Contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-auto-translator
 *
 * @package WpAutoTranslator
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('WAT_VERSION', '0.1.0');
define('WAT_FILE', __FILE__);
define('WAT_DIR', __DIR__);

if (is_file(WAT_DIR . '/vendor/autoload.php')) {
    require_once WAT_DIR . '/vendor/autoload.php';
}

require_once WAT_DIR . '/src/Autoloader.php';
WpAutoTranslator\Autoloader::register();

register_activation_hook(WAT_FILE, [WpAutoTranslator\Activator::class, 'activate']);
register_deactivation_hook(WAT_FILE, [WpAutoTranslator\Deactivator::class, 'deactivate']);

add_action('plugins_loaded', static function (): void {
    WpAutoTranslator\Plugin::instance()->boot();
});
