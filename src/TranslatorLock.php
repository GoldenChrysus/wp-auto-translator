<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * Stops the save watcher from treating a generated translation as a new source post.
 */
final class TranslatorLock
{
    private static bool $active = false;

    public static function enable(): void
    {
        self::$active = true;
    }

    public static function disable(): void
    {
        self::$active = false;
    }

    public static function isActive(): bool
    {
        return self::$active;
    }
}
