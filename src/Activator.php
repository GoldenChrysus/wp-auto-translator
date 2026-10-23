<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class Activator
{
    public static function activate(): void
    {
        Schema::install();
        add_option(Options::BASE_LOCALE, '');
        add_option(Options::TARGET_LOCALES, []);
        add_option(Options::AUTO_TRANSLATE, '0');
        add_option(Options::MODEL, 'gpt-6-luna');
        add_option(Options::SETUP_COMPLETE, '0');
        add_option(Options::SETUP_STEP, '1');
        add_option(Options::API_KEY, '', '', false);

        if (Options::baseLocale() === '') {
            set_transient('wat_activation_redirect', '1', 60);
        }
    }
}
