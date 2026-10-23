<?php

declare(strict_types=1);

namespace WpAutoTranslator\Admin;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class Notice
{
    public static function flash(string $type, string $message): void
    {
        set_transient(self::key(), [
            'type' => $type,
            'message' => $message,
        ], 60);
    }

    public static function render(): void
    {
        $notice = get_transient(self::key());
        if (! is_array($notice) || ! isset($notice['message'], $notice['type'])) {
            return;
        }
        if (! is_string($notice['message']) || ! is_string($notice['type'])) {
            return;
        }

        delete_transient(self::key());
        wp_admin_notice($notice['message'], ['type' => $notice['type']]);
    }

    private static function key(): string
    {
        return 'wat_notice_' . get_current_user_id();
    }
}
