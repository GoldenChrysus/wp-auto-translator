<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class TranslationRecord
{
    public function __construct(
        public readonly int $id,
        public readonly string $groupId,
        public readonly int $postId,
        public readonly string $locale,
        public readonly int $sourcePostId,
        public readonly string $status,
        public readonly string $sourceHash,
        public readonly string $lastError,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    public static function fromRow(object $row): self
    {
        return new self(
            (int) $row->id,
            (string) $row->group_id,
            (int) $row->post_id,
            (string) $row->locale,
            (int) $row->source_post_id,
            (string) $row->status,
            (string) $row->source_hash,
            (string) ($row->last_error ?? ''),
            (string) $row->created_at,
            (string) $row->updated_at,
        );
    }

    public function isOriginal(): bool
    {
        return $this->status === 'original' && $this->sourcePostId === 0;
    }
}
