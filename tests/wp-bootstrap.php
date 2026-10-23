<?php

declare(strict_types=1);

$testsDir = getenv('WP_TESTS_DIR');
if (! is_string($testsDir) || ! file_exists($testsDir . '/includes/functions.php')) {
    fwrite(STDERR, "WP_TESTS_DIR is not set. Run npm run env:start:tests inside an environment with Docker.\n");
    exit(1);
}

require dirname(__DIR__) . '/vendor/autoload.php';

$polyfills = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
if (is_string($polyfills) && $polyfills !== '') {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $polyfills);
}

require $testsDir . '/includes/functions.php';

tests_add_filter('muplugins_loaded', static function (): void {
    require dirname(__DIR__) . '/wp-auto-translator.php';
});

require $testsDir . '/includes/bootstrap.php';
