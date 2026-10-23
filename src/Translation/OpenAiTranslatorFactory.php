<?php

declare(strict_types=1);

namespace WpAutoTranslator\Translation;

use WpAutoTranslator\Options;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class OpenAiTranslatorFactory implements TranslatorFactory
{
    public function make(): Translator
    {
        $key = Options::apiKey();
        if ($key === '') {
            throw new TranslationException('Add an OpenAI API key before translating.');
        }

        return new OpenAiTranslator($key, Options::model());
    }
}
