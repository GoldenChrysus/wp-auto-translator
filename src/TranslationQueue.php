<?php

declare(strict_types=1);

namespace WpAutoTranslator;

use WpAutoTranslator\Translation\TranslationException;
use WpAutoTranslator\Translation\TranslationProcessor;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class TranslationQueue
{
    public function __construct(
        private TranslationRepository $repository,
        private TranslationProcessor $processor,
    ) {
    }

    public function enqueueRecord(TranslationRecord $original): int
    {
        if (! $original->isOriginal()) {
            return 0;
        }

        $post = get_post($original->postId);
        if (! $post instanceof \WP_Post) {
            return 0;
        }

        $hash = ContentHasher::hash($post->post_title, $post->post_content, $post->post_excerpt);
        $enqueued = 0;

        foreach (Options::targetLocales() as $locale) {
            $existing = $this->repository->findByGroupAndLocale($original->groupId, $locale);
            if ($existing instanceof TranslationRecord) {
                if ($existing->status === 'translating') {
                    continue;
                }
                if ($existing->status === 'translated' && $existing->sourceHash === $hash) {
                    continue;
                }
                if ($existing->status === 'pending') {
                    continue;
                }
                $this->repository->markPending($existing->id);
                $enqueued++;
                continue;
            }

            try {
                $created = $this->processor->ensureTranslation($original, $locale);
            } catch (TranslationException) {
                continue;
            }
            if ($created->status !== 'pending') {
                $this->repository->markPending($created->id);
            }
            $enqueued++;
        }

        if ($enqueued > 0) {
            Cron::scheduleSoon();
        }

        return $enqueued;
    }

    /**
     * @return array{enqueued: int, lastId: int, done: bool, pending: int}
     */
    public function enqueueBatch(int $afterPostId, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $originals = $this->repository->originalsAfter(Options::baseLocale(), $afterPostId, $limit);
        $enqueued = 0;
        $lastId = $afterPostId;

        foreach ($originals as $original) {
            $lastId = $original->postId;
            $post = get_post($original->postId);
            if (! $post instanceof \WP_Post || $post->post_status !== 'publish') {
                continue;
            }
            $enqueued += $this->enqueueRecord($original);
        }

        return [
            'enqueued' => $enqueued,
            'lastId' => $lastId,
            'done' => count($originals) < $limit,
            'pending' => $this->repository->countPending(),
        ];
    }
}
