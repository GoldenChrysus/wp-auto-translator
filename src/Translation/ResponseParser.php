<?php

declare(strict_types=1);

namespace WpAutoTranslator\Translation;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class ResponseParser
{
    public function parse(string $raw): TranslationPayload
    {
        $json = trim($raw);
        if (str_starts_with($json, '```')) {
            $json = preg_replace('/^```(?:json)?\s*/i', '', $json) ?? $json;
            $json = preg_replace('/\s*```$/', '', $json) ?? $json;
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            $start = strpos($json, '{');
            $end = strrpos($json, '}');
            if ($start !== false && $end !== false && $end > $start) {
                $decoded = json_decode(substr($json, $start, $end - $start + 1), true);
            }
        }

        if (! is_array($decoded)) {
            throw new TranslationException('The model did not return JSON.');
        }

        foreach (['title', 'content', 'excerpt'] as $key) {
            if (! array_key_exists($key, $decoded) || ! is_string($decoded[$key])) {
                throw new TranslationException(sprintf('The model response is missing a string "%s".', $key));
            }
        }

        $title = trim($decoded['title']);
        if ($title === '') {
            throw new TranslationException('The model returned an empty title.');
        }

        if (mb_strlen($title) > 400) {
            throw new TranslationException('The translated title is too long.');
        }

        return new TranslationPayload($title, $decoded['content'], trim($decoded['excerpt']));
    }
}
