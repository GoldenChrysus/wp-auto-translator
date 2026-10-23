<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * Map of posts to locales. One row per post. Originals have source_post_id 0.
 */
final class Schema
{
    public const TABLE = 'wat_translations';
    public const DB_VERSION = '1';
    public const VERSION_OPTION = 'wat_db_version';

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE;
    }

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            group_id char(36) NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            locale varchar(20) NOT NULL,
            source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'original',
            source_hash char(64) NOT NULL DEFAULT '',
            last_error text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY post_id (post_id),
            KEY group_locale (group_id, locale),
            KEY locale_status (locale, status),
            KEY status_updated (status, updated_at),
            KEY source_post_id (source_post_id)
        ) {$charset};";

        dbDelta($sql);
        update_option(self::VERSION_OPTION, self::DB_VERSION);
    }

    public static function maybeUpgrade(): void
    {
        $installed = get_option(self::VERSION_OPTION, '');
        if ($installed !== self::DB_VERSION) {
            self::install();
        }
    }
}
