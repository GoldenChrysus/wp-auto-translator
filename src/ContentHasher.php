<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * Fingerprint of the fields that are sent to the translator.
 * A translation is outdated when its stored hash no longer matches the source post.
 */
final class ContentHasher
{
    public static function hash(string $title, string $content, string $excerpt): string
    {
        return hash('sha256', $title . "\n" . $content . "\n" . $excerpt);
    }
}
