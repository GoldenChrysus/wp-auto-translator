<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * Locales the plugin knows how to store and ask a model to translate.
 *
 * Codes follow WordPress locale identifiers. Hyphenated input such as en-US is accepted.
 */
final class LocaleRegistry
{
    /**
     * @var array<string, array{label: string, language: string}>
     */
    private const LOCALES = [
        'en_US' => ['label' => 'English (United States)', 'language' => 'English'],
        'en_GB' => ['label' => 'English (United Kingdom)', 'language' => 'English'],
        'ja' => ['label' => 'Japanese', 'language' => 'Japanese'],
        'ko_KR' => ['label' => 'Korean', 'language' => 'Korean'],
        'zh_CN' => ['label' => 'Chinese (Simplified)', 'language' => 'Chinese (Simplified)'],
        'zh_TW' => ['label' => 'Chinese (Traditional)', 'language' => 'Chinese (Traditional)'],
        'fr_FR' => ['label' => 'French', 'language' => 'French'],
        'de_DE' => ['label' => 'German', 'language' => 'German'],
        'es_ES' => ['label' => 'Spanish (Spain)', 'language' => 'Spanish'],
        'es_MX' => ['label' => 'Spanish (Mexico)', 'language' => 'Spanish'],
        'pt_BR' => ['label' => 'Portuguese (Brazil)', 'language' => 'Portuguese'],
        'pt_PT' => ['label' => 'Portuguese (Portugal)', 'language' => 'Portuguese'],
        'it_IT' => ['label' => 'Italian', 'language' => 'Italian'],
        'nl_NL' => ['label' => 'Dutch', 'language' => 'Dutch'],
        'pl_PL' => ['label' => 'Polish', 'language' => 'Polish'],
        'ru_RU' => ['label' => 'Russian', 'language' => 'Russian'],
        'uk' => ['label' => 'Ukrainian', 'language' => 'Ukrainian'],
        'sv_SE' => ['label' => 'Swedish', 'language' => 'Swedish'],
        'da_DK' => ['label' => 'Danish', 'language' => 'Danish'],
        'fi' => ['label' => 'Finnish', 'language' => 'Finnish'],
        'nb_NO' => ['label' => 'Norwegian (Bokmål)', 'language' => 'Norwegian'],
        'ar' => ['label' => 'Arabic', 'language' => 'Arabic'],
        'he_IL' => ['label' => 'Hebrew', 'language' => 'Hebrew'],
        'hi_IN' => ['label' => 'Hindi', 'language' => 'Hindi'],
        'th' => ['label' => 'Thai', 'language' => 'Thai'],
        'vi' => ['label' => 'Vietnamese', 'language' => 'Vietnamese'],
        'id_ID' => ['label' => 'Indonesian', 'language' => 'Indonesian'],
        'tr_TR' => ['label' => 'Turkish', 'language' => 'Turkish'],
    ];

    /**
     * @return array<string, array{label: string, language: string}>
     */
    public function all(): array
    {
        return self::LOCALES;
    }

    public function normalize(string $input): ?string
    {
        $candidate = str_replace('-', '_', trim($input));
        if ($candidate === '') {
            return null;
        }

        foreach (self::LOCALES as $code => $meta) {
            unset($meta);
            if (strcasecmp($code, $candidate) === 0) {
                return $code;
            }
        }

        return null;
    }

    public function label(string $code): string
    {
        return self::LOCALES[$code]['label'] ?? $code;
    }

    public function languageName(string $code): string
    {
        return self::LOCALES[$code]['language'] ?? $code;
    }
}
