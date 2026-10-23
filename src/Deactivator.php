<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class Deactivator
{
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(Cron::HOOK);
    }
}
