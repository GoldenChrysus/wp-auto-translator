<?php

declare(strict_types=1);

namespace WpAutoTranslator\Admin;

use WpAutoTranslator\Capabilities;
use WpAutoTranslator\Options;
use WpAutoTranslator\Translation\OpenAiTranslator;
use WpAutoTranslator\Translation\ProcessResult;
use WpAutoTranslator\Translation\TranslationException;
use WpAutoTranslator\Translation\TranslationProcessor;
use WpAutoTranslator\Translation\TranslatorFactory;
use WpAutoTranslator\TranslationQueue;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class Ajax
{
    public function __construct(
        private TranslationProcessor $processor,
        private TranslationQueue $queue,
        private TranslatorFactory $factory,
    ) {
    }

    public function translateNow(): void
    {
        if (! Capabilities::currentUserCan()) {
            wp_die(esc_html__('You cannot manage translations.', 'wp-auto-translator'));
        }
        check_admin_referer('wat_translate_now');

        $job = isset($_POST['wat_job']) ? sanitize_text_field(wp_unslash((string) $_POST['wat_job'])) : '';
        $parts = explode('|', $job, 2);
        $postId = isset($parts[0]) ? (int) $parts[0] : 0;
        $locale = $parts[1] ?? '';

        try {
            $record = $this->processor->translateNow($postId, $locale);
            Notice::flash(
                'success',
                sprintf(
                    /* translators: %s is a locale code such as ja. */
                    __('Translated the post to %s.', 'wp-auto-translator'),
                    $record->locale
                )
            );
        } catch (TranslationException $exception) {
            Notice::flash('error', $exception->getMessage());
        }

        wp_safe_redirect(admin_url('admin.php?page=wat-translations'));
        exit;
    }

    public function enqueueBatch(): void
    {
        check_ajax_referer('wat_admin');
        if (! Capabilities::currentUserCan()) {
            wp_send_json_error(['message' => __('You cannot manage translations.', 'wp-auto-translator')], 403);
        }
        if (! Options::isReady()) {
            wp_send_json_error(['message' => __('Set a base locale first.', 'wp-auto-translator')], 400);
        }
        if (Options::targetLocales() === []) {
            wp_send_json_error(['message' => __('Choose at least one target language.', 'wp-auto-translator')], 400);
        }
        if (Options::apiKey() === '') {
            wp_send_json_error(['message' => __('Add an OpenAI API key before translating.', 'wp-auto-translator')], 400);
        }

        $after = isset($_POST['after']) ? (int) $_POST['after'] : 0;
        wp_send_json_success($this->queue->enqueueBatch($after));
    }

    public function processNext(): void
    {
        $this->assertAjax();

        try {
            $result = $this->processor->processNext();
        } catch (TranslationException $exception) {
            wp_send_json_error(['message' => $exception->getMessage()], 500);
        }

        wp_send_json_success($this->resultPayload($result));
    }

    public function testApi(): void
    {
        $this->assertAjax();

        try {
            $translator = $this->factory->make();
            if (! $translator instanceof OpenAiTranslator) {
                throw new TranslationException(
                    __('The connection check is only available for the OpenAI translator.', 'wp-auto-translator')
                );
            }
            $translator->ping();
        } catch (TranslationException $exception) {
            wp_send_json_error(['message' => $exception->getMessage()], 400);
        }

        wp_send_json_success(['message' => __('OpenAI accepted the API key.', 'wp-auto-translator')]);
    }

    private function assertAjax(): void
    {
        check_ajax_referer('wat_admin');
        if (! Capabilities::currentUserCan()) {
            wp_send_json_error(['message' => __('You cannot manage translations.', 'wp-auto-translator')], 403);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resultPayload(ProcessResult $result): array
    {
        $record = $result->record;
        return [
            'processed' => $result->processed,
            'remaining' => $result->remaining,
            'postId' => $record?->sourcePostId ?? 0,
            'locale' => $record?->locale ?? '',
            'status' => $record?->status ?? '',
            'error' => $result->error,
        ];
    }
}
