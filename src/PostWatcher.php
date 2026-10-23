<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * Associates new posts with the base locale and, when enabled, queues translations on publish.
 */
final class PostWatcher
{
    public function __construct(
        private TranslationRepository $repository,
        private TranslationQueue $queue,
    ) {
    }

    public function register(): void
    {
        add_action('wp_after_insert_post', [$this, 'handle'], 20, 4);
        add_action('before_delete_post', [$this, 'forget']);
    }

    public function forget(int $postId): void
    {
        $this->repository->deleteByPostId($postId);
    }

    public function handle(int $postId, \WP_Post $post, bool $update, ?\WP_Post $postBefore): void
    {
        unset($update, $postBefore);

        if (TranslatorLock::isActive() || ! Options::isReady()) {
            return;
        }
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! in_array($post->post_type, PostTypes::all(), true)) {
            return;
        }
        if (in_array($post->post_status, ['auto-draft', 'trash', 'inherit', 'wat-pending'], true)) {
            return;
        }

        $existing = $this->repository->findByPostId($postId);
        if ($existing instanceof TranslationRecord && ! $existing->isOriginal()) {
            return;
        }

        if (! $existing instanceof TranslationRecord) {
            $existing = $this->repository->insertOriginal(
                $postId,
                Options::baseLocale(),
                ContentHasher::hash($post->post_title, $post->post_content, $post->post_excerpt)
            );
        }

        if (! Options::autoTranslate() || $post->post_status !== 'publish') {
            return;
        }

        $this->queue->enqueueRecord($existing);
    }
}
