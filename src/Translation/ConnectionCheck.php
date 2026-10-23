<?php

declare(strict_types=1);

namespace WpAutoTranslator\Translation;

use NeuronAI\StructuredOutput\SchemaProperty;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class ConnectionCheck
{
    public function __construct(
        #[SchemaProperty(description: 'True when the API key was accepted.', required: true)]
        public bool $ok = false,
    ) {
    }
}
