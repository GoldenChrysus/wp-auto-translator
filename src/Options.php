<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * Plugin settings. The API key is stored as a non-autoloaded option.
 *
 * Define WAT_OPENAI_API_KEY in wp-config.php to keep the key out of the database.
 */
final class Options
{
    public const BASE_LOCALE = 'wat_base_locale';
    public const TARGET_LOCALES = 'wat_target_locales';
    public const AUTO_TRANSLATE = 'wat_auto_translate';
    public const MODEL = 'wat_openai_model';
    public const API_KEY = 'wat_openai_api_key';
    public const SETUP_COMPLETE = 'wat_setup_complete';
    public const SETUP_STEP = 'wat_setup_step';

    public static function isReady(): bool
    {
        return self::baseLocale() !== '';
    }

    public static function baseLocale(): string
    {
        $value = get_option(self::BASE_LOCALE, '');
        return is_string($value) ? $value : '';
    }

    /**
     * @return list<string>
     */
    public static function targetLocales(): array
    {
        $value = get_option(self::TARGET_LOCALES, []);
        if (! is_array($value)) {
            return [];
        }

        $locales = [];
        foreach ($value as $locale) {
            if (is_string($locale) && $locale !== '') {
                $locales[] = $locale;
            }
        }

        return array_values(array_unique($locales));
    }

    public static function autoTranslate(): bool
    {
        return get_option(self::AUTO_TRANSLATE, '0') === '1';
    }

    public static function model(): string
    {
        $value = get_option(self::MODEL, 'gpt-6-luna');
        return is_string($value) && $value !== '' ? $value : 'gpt-6-luna';
    }

    public static function apiKey(): string
    {
        if (defined('WAT_OPENAI_API_KEY') && is_string(WAT_OPENAI_API_KEY) && WAT_OPENAI_API_KEY !== '') {
            return WAT_OPENAI_API_KEY;
        }

        $value = get_option(self::API_KEY, '');
        return is_string($value) ? $value : '';
    }

    public static function apiKeyIsConstant(): bool
    {
        return defined('WAT_OPENAI_API_KEY') && is_string(WAT_OPENAI_API_KEY) && WAT_OPENAI_API_KEY !== '';
    }

    public static function setupComplete(): bool
    {
        return get_option(self::SETUP_COMPLETE, '0') === '1';
    }

    public static function setupStep(): int
    {
        $step = (int) get_option(self::SETUP_STEP, 1);
        return max(1, min(5, $step));
    }
}
