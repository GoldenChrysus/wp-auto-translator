<?php

declare(strict_types=1);

namespace WpAutoTranslator\Translation;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class PromptBuilder
{
    public function system(
        string $sourceLocale,
        string $sourceLanguage,
        string $targetLocale,
        string $targetLanguage
    ): string {
        return <<<PROMPT
        You translate WordPress posts from {$sourceLanguage} ({$sourceLocale}) into {$targetLanguage} ({$targetLocale}).

        Rules:
        - Preserve HTML tags and attributes.
        - Preserve Gutenberg block comments exactly, including <!-- wp:paragraph --> and closing comments.
        - Preserve shortcodes such as [gallery] and their attributes.
        - Preserve URLs, email addresses, and code spans.
        - Translate only human-readable text.
        - Do not add commentary.
        - Return a JSON object with the keys "title", "content", and "excerpt".
        - "title" and "excerpt" are plain text. "content" keeps the original HTML structure.
        PROMPT;
    }

    public function user(TranslationPayload $payload): string
    {
        try {
            $encoded = json_encode(
                [
                    'title' => $payload->title,
                    'content' => $payload->content,
                    'excerpt' => $payload->excerpt,
                ],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new TranslationException('The post could not be encoded for translation.', 0, $exception);
        }

        return "Translate this post to json:\n" . $encoded;
    }
}
