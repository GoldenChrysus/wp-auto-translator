<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class Capabilities
{
    public static function required(): string
    {
        $capability = apply_filters('wat_capability', 'manage_options');
        return is_string($capability) && $capability !== '' ? $capability : 'manage_options';
    }

    public static function currentUserCan(): bool
    {
        return current_user_can(self::required());
    }
}
