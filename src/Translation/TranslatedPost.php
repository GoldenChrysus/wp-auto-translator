<?php

declare(strict_types=1);

namespace WpAutoTranslator\Translation;

use NeuronAI\StructuredOutput\SchemaProperty;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * Schema Neuron sends to the model. json_schema does not require the word "json"
 * in the input, and it does not send temperature.
 */
final class TranslatedPost
{
    public function __construct(
        #[SchemaProperty(description: 'Translated title as plain text.', required: true)]
        public string $title = '',
        #[SchemaProperty(
            description: 'Translated content. Preserve HTML, Gutenberg block comments, shortcodes, and URLs.',
            required: true,
        )]
        public string $content = '',
        #[SchemaProperty(description: 'Translated excerpt as plain text.', required: true)]
        public string $excerpt = '',
    ) {
    }
}
