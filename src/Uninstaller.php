<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class Uninstaller
{
    public static function run(): void
    {
        if (is_multisite()) {
            $siteIds = get_sites([
                'fields' => 'ids',
                'number' => 0,
            ]);
            foreach ($siteIds as $siteId) {
                switch_to_blog((int) $siteId);
                self::deleteSiteData();
                restore_current_blog();
            }
            return;
        }

        self::deleteSiteData();
    }

    private static function deleteSiteData(): void
    {
        global $wpdb;

        $options = [
            Options::BASE_LOCALE,
            Options::TARGET_LOCALES,
            Options::AUTO_TRANSLATE,
            Options::MODEL,
            Options::API_KEY,
            Options::SETUP_COMPLETE,
            Options::SETUP_STEP,
            Schema::VERSION_OPTION,
        ];

        foreach ($options as $option) {
            delete_option($option);
        }

        $table = Schema::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a prefixed constant.
        $wpdb->query("DROP TABLE IF EXISTS {$table}");
        wp_clear_scheduled_hook(Cron::HOOK);
    }
}
