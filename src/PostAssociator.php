<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class PostAssociator
{
    public function __construct(private TranslationRepository $repository)
    {
    }

    /**
     * Tags posts that do not yet belong to a locale. Safe to run more than once.
     */
    public function associateExisting(string $locale): int
    {
        $associated = 0;
        $page = 1;

        do {
            $ids = get_posts([
                'post_type' => PostTypes::all(),
                'post_status' => ['publish', 'draft', 'pending', 'private', 'future'],
                'posts_per_page' => 200,
                'paged' => $page,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
                'suppress_filters' => true,
            ]);

            if (! is_array($ids) || $ids === []) {
                break;
            }

            foreach ($ids as $id) {
                $postId = (int) $id;
                if ($this->repository->findByPostId($postId) instanceof TranslationRecord) {
                    continue;
                }
                $post = get_post($postId);
                if (! $post instanceof \WP_Post) {
                    continue;
                }
                $this->repository->insertOriginal(
                    $postId,
                    $locale,
                    ContentHasher::hash($post->post_title, $post->post_content, $post->post_excerpt)
                );
                $associated++;
            }

            $page++;
        } while (count($ids) === 200 && $page <= 50);

        return $associated;
    }
}
