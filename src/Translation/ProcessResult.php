<?php

declare(strict_types=1);

namespace WpAutoTranslator\Translation;

use WpAutoTranslator\TranslationRecord;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class ProcessResult
{
    private function __construct(
        public readonly bool $processed,
        public readonly ?TranslationRecord $record,
        public readonly int $remaining,
        public readonly string $error = '',
    ) {
    }

    public static function idle(int $remaining = 0): self
    {
        return new self(false, null, $remaining);
    }

    public static function done(TranslationRecord $record, int $remaining, string $error = ''): self
    {
        return new self(true, $record, $remaining, $error);
    }
}
