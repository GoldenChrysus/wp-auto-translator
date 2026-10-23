<?php

declare(strict_types=1);

namespace WpAutoTranslator\Translation;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class TranslationPayload
{
    public function __construct(
        public readonly string $title,
        public readonly string $content,
        public readonly string $excerpt,
    ) {
    }
}
