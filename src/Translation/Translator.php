<?php

declare(strict_types=1);

namespace WpAutoTranslator\Translation;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * One structured translation call. An agent framework can implement this later
 * without the rest of the plugin knowing about it.
 */
interface Translator
{
    public function translate(
        string $sourceLocale,
        string $sourceLanguage,
        string $targetLocale,
        string $targetLanguage,
        TranslationPayload $payload
    ): TranslationPayload;
}
