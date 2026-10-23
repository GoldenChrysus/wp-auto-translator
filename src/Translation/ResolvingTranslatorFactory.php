<?php

declare(strict_types=1);

namespace WpAutoTranslator\Translation;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * Lets another plugin replace the translator at call time via wat_translator_factory.
 * Register the filter from a normal plugin; it does not have to run before this plugin boots.
 */
final class ResolvingTranslatorFactory implements TranslatorFactory
{
    public function __construct(private OpenAiTranslatorFactory $default)
    {
    }

    public function make(): Translator
    {
        $factory = $this->default;
        if (function_exists('apply_filters')) {
            $filtered = apply_filters('wat_translator_factory', $this->default);
            if ($filtered instanceof TranslatorFactory && ! $filtered instanceof self) {
                $factory = $filtered;
            }
        }

        return $factory->make();
    }
}
