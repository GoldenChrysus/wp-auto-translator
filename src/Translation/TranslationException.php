<?php

declare(strict_types=1);

namespace WpAutoTranslator\Translation;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class TranslationException extends \RuntimeException
{
}
