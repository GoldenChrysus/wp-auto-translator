<?php

declare(strict_types=1);

namespace WpAutoTranslator\Translation;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

interface TranslatorFactory
{
    /**
     * @throws TranslationException When the translator cannot be constructed.
     */
    public function make(): Translator;
}
