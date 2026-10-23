<?php

declare(strict_types=1);

namespace WpAutoTranslator;

use WpAutoTranslator\Admin\Ajax;
use WpAutoTranslator\Admin\Menu;
use WpAutoTranslator\Translation\OpenAiTranslatorFactory;
use WpAutoTranslator\Translation\ResolvingTranslatorFactory;
use WpAutoTranslator\Translation\TranslationProcessor;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class Plugin
{
    private static ?self $instance = null;

    private bool $booted = false;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->booted = true;

        Schema::maybeUpgrade();
        add_action('init', [$this, 'loadTextdomain']);

        $locales = new LocaleRegistry();
        $repository = new TranslationRepository();
        $factory = new ResolvingTranslatorFactory(new OpenAiTranslatorFactory());
        $processor = new TranslationProcessor($repository, $factory, $locales);
        $queue = new TranslationQueue($repository, $processor);

        (new Cron($processor, $repository))->register();
        (new LocaleQuery($locales, $repository))->register();
        (new PostWatcher($repository, $queue))->register();

        if (is_admin()) {
            $menu = new Menu($locales, $repository);
            $ajax = new Ajax($processor, $queue, $factory);
            add_action('admin_menu', [$menu, 'register']);
            add_action('admin_init', [$menu, 'handleRequests']);
            add_action('admin_notices', [$menu, 'notices']);
            add_action('admin_enqueue_scripts', [$menu, 'assets']);
            add_action('admin_post_wat_translate_now', [$ajax, 'translateNow']);
            add_action('wp_ajax_wat_enqueue_batch', [$ajax, 'enqueueBatch']);
            add_action('wp_ajax_wat_process_next', [$ajax, 'processNext']);
            add_action('wp_ajax_wat_test_api', [$ajax, 'testApi']);
        }
    }

    public function loadTextdomain(): void
    {
        load_plugin_textdomain('wp-auto-translator', false, dirname(plugin_basename(WAT_FILE)) . '/languages');
    }
}
