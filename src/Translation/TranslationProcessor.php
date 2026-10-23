<?php

declare(strict_types=1);

namespace WpAutoTranslator\Translation;

use WpAutoTranslator\ContentHasher;
use WpAutoTranslator\HtmlComments;
use WpAutoTranslator\LocaleRegistry;
use WpAutoTranslator\Options;
use WpAutoTranslator\TranslationRecord;
use WpAutoTranslator\TranslationRepository;
use WpAutoTranslator\TranslatorLock;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class TranslationProcessor
{
    public function __construct(
        private TranslationRepository $repository,
        private TranslatorFactory $factory,
        private LocaleRegistry $locales,
    ) {
    }

    public function translateNow(int $sourcePostId, string $targetLocale): TranslationRecord
    {
        $source = $this->requireOriginal($sourcePostId);
        $locale = $this->requireTarget($targetLocale);
        $record = $this->ensureTranslation($source, $locale);
        if (! $this->repository->claimById($record->id)) {
            throw new TranslationException('This translation is already running.');
        }

        $claimed = $this->repository->findById($record->id);
        if (! $claimed instanceof TranslationRecord) {
            throw new TranslationException('The translation record disappeared.');
        }

        return $this->translateClaimed($source, $claimed);
    }

    public function processNext(): ProcessResult
    {
        $claimed = $this->repository->claimNextPending();
        if (! $claimed instanceof TranslationRecord) {
            return ProcessResult::idle(0);
        }

        $source = $this->repository->findByPostId($claimed->sourcePostId);
        if (! $source instanceof TranslationRecord || ! $source->isOriginal()) {
            $this->repository->markFailed($claimed->id, 'The source post is no longer a base-language post.');
            return ProcessResult::done(
                $this->mustFind($claimed->id),
                $this->repository->countPending(),
                'The source post is no longer a base-language post.'
            );
        }

        try {
            $updated = $this->translateClaimed($source, $claimed);
            return ProcessResult::done($updated, $this->repository->countPending());
        } catch (TranslationException $exception) {
            $this->repository->markFailed($claimed->id, $exception->getMessage());
            return ProcessResult::done(
                $this->mustFind($claimed->id),
                $this->repository->countPending(),
                $exception->getMessage()
            );
        }
    }

    private function translateClaimed(TranslationRecord $source, TranslationRecord $translation): TranslationRecord
    {
        try {
            $post = get_post($source->postId);
            $translationPost = get_post($translation->postId);
            if (! $post instanceof \WP_Post || ! $translationPost instanceof \WP_Post) {
                throw new TranslationException('The source or translation post no longer exists.');
            }

            $hash = ContentHasher::hash($post->post_title, $post->post_content, $post->post_excerpt);
            $payload = new TranslationPayload($post->post_title, $post->post_content, $post->post_excerpt);
            $translated = $this->factory->make()->translate(
                $source->locale,
                $this->locales->languageName($source->locale),
                $translation->locale,
                $this->locales->languageName($translation->locale),
                $payload
            );
            $this->saveTranslation($translationPost->ID, $post, $translated, $translation->locale);
            $this->repository->markTranslated($translation->id, $hash);
        } catch (TranslationException $exception) {
            $this->repository->markFailed($translation->id, $exception->getMessage());
            throw $exception;
        }

        return $this->mustFind($translation->id);
    }

    public function ensureTranslation(TranslationRecord $source, string $locale): TranslationRecord
    {
        $existing = $this->repository->findByGroupAndLocale($source->groupId, $locale);
        if ($existing instanceof TranslationRecord) {
            return $existing;
        }

        $post = get_post($source->postId);
        if (! $post instanceof \WP_Post) {
            throw new TranslationException('The source post no longer exists.');
        }

        $slug = $post->post_name !== '' ? $post->post_name . '-' . strtolower($locale) : strtolower($locale);

        TranslatorLock::enable();
        try {
            $postId = wp_insert_post(wp_slash([
                'post_type' => $post->post_type,
                'post_status' => 'wat-pending',
                'post_title' => $post->post_title,
                'post_content' => '',
                'post_excerpt' => '',
                'post_name' => $slug,
                'post_author' => (int) $post->post_author,
            ]), true);

            if (is_wp_error($postId)) {
                throw new TranslationException($postId->get_error_message());
            }

            return $this->repository->insert(
                $source->groupId,
                (int) $postId,
                $locale,
                $source->postId,
                'pending',
                ''
            );
        } finally {
            TranslatorLock::disable();
        }
    }

    private function saveTranslation(int $postId, \WP_Post $source, TranslationPayload $translated, string $locale): void
    {
        $content = HtmlComments::preserve($translated->content, 'wp_kses_post');
        $excerpt = wp_kses_post($translated->excerpt);
        $title = sanitize_text_field($translated->title);
        $slug = sanitize_title($title);
        if ($slug === '') {
            $slug = ($source->post_name !== '' ? $source->post_name : 'translation') . '-' . strtolower($locale);
        }

        $status = $source->post_status === 'publish' ? 'publish' : $source->post_status;
        if (! in_array($status, ['publish', 'draft', 'pending', 'private', 'future'], true)) {
            $status = 'draft';
        }

        $postarr = [
            'ID' => $postId,
            'post_title' => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_name' => $slug,
            'post_status' => $status,
        ];
        $postarr = apply_filters('wat_translation_post_data', $postarr, $source->ID, $locale);
        if (! is_array($postarr)) {
            $postarr = [];
        }
        $postarr['ID'] = $postId;

        TranslatorLock::enable();
        try {
            $result = wp_update_post(wp_slash($postarr), true);
        } finally {
            TranslatorLock::disable();
        }

        if (is_wp_error($result)) {
            throw new TranslationException($result->get_error_message());
        }
    }

    private function requireOriginal(int $postId): TranslationRecord
    {
        $record = $this->repository->findByPostId($postId);
        if (! $record instanceof TranslationRecord || ! $record->isOriginal()) {
            throw new TranslationException('Only a base-language post can be translated.');
        }
        if ($record->locale !== Options::baseLocale()) {
            throw new TranslationException('This post is not in the current base locale.');
        }

        return $record;
    }

    private function requireTarget(string $locale): string
    {
        $normalized = $this->locales->normalize($locale);
        if ($normalized === null || ! in_array($normalized, Options::targetLocales(), true)) {
            throw new TranslationException('That locale is not a configured target language.');
        }

        return $normalized;
    }

    private function mustFind(int $id): TranslationRecord
    {
        $record = $this->repository->findById($id);
        if (! $record instanceof TranslationRecord) {
            throw new TranslationException('The translation record disappeared.');
        }

        return $record;
    }
}
