<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class SettingsSanitizer
{
    public function __construct(private LocaleRegistry $locales)
    {
    }

    public function locale(string $input): ?string
    {
        return $this->locales->normalize($input);
    }

    /**
     * @param list<mixed> $input
     * @return list<string>
     */
    public function targetLocales(array $input, string $baseLocale): array
    {
        $targets = [];
        foreach ($input as $value) {
            if (! is_string($value)) {
                continue;
            }
            $locale = $this->locales->normalize($value);
            if ($locale === null || $locale === $baseLocale) {
                continue;
            }
            $targets[] = $locale;
        }

        return array_values(array_unique($targets));
    }

    public function model(string $input): string
    {
        $model = trim($input);
        if (preg_match('/^[A-Za-z0-9._-]{1,64}$/', $model) !== 1) {
            return 'gpt-6-luna';
        }

        return $model;
    }

    /**
     * @throws \InvalidArgumentException When the key is present but unusable.
     */
    public function apiKey(string $input): string
    {
        $key = trim($input);
        if ($key === '') {
            return '';
        }

        if (strlen($key) < 20 || strlen($key) > 300 || preg_match('/\s/', $key) === 1) {
            throw new \InvalidArgumentException('The OpenAI API key does not look valid.');
        }

        return $key;
    }
}
