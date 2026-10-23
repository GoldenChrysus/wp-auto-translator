<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

// Table names are a prefixed constant and cannot be prepare() placeholders. Query values are.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

final class TranslationRepository
{
    public function findById(int $id): ?TranslationRecord
    {
        global $wpdb;
        $table = Schema::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a prefixed constant.
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
        return $row ? TranslationRecord::fromRow($row) : null;
    }

    public function findByPostId(int $postId): ?TranslationRecord
    {
        global $wpdb;
        $table = Schema::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE post_id = %d", $postId));
        return $row ? TranslationRecord::fromRow($row) : null;
    }

    public function findByGroupAndLocale(string $groupId, string $locale): ?TranslationRecord
    {
        global $wpdb;
        $table = Schema::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE group_id = %s AND locale = %s LIMIT 1",
                $groupId,
                $locale
            )
        );
        return $row ? TranslationRecord::fromRow($row) : null;
    }

    /**
     * Posts in $locale with one of these slugs, plus translations whose base post has one of these slugs.
     *
     * @param list<string> $slugs
     * @return list<int>
     */
    public function postIdsForLocaleSlugs(string $postType, string $locale, array $slugs): array
    {
        $slugs = array_values(array_filter(
            $slugs,
            static fn (string $slug): bool => $slug !== ''
        ));
        if ($postType === '' || $locale === '' || $slugs === []) {
            return [];
        }

        global $wpdb;
        $table = Schema::table();
        $placeholders = implode(',', array_fill(0, count($slugs), '%s'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT target.post_id
                FROM {$wpdb->posts} slug_post
                INNER JOIN {$table} slug_row ON slug_row.post_id = slug_post.ID
                INNER JOIN {$table} target
                    ON target.locale = %s
                    AND (
                        target.post_id = slug_post.ID
                        OR (slug_row.source_post_id = 0 AND target.source_post_id = slug_post.ID)
                    )
                WHERE slug_post.post_type = %s
                    AND slug_post.post_status NOT IN ('trash', 'auto-draft')
                    AND slug_post.post_name IN ({$placeholders})",
                $locale,
                $postType,
                ...$slugs
            )
        );

        return array_values(array_map('intval', $ids));
    }

    public function insertOriginal(int $postId, string $locale, string $hash): TranslationRecord
    {
        $existing = $this->findByPostId($postId);
        if ($existing instanceof TranslationRecord) {
            return $existing;
        }

        return $this->insert(
            wp_generate_uuid4(),
            $postId,
            $locale,
            0,
            'original',
            $hash
        );
    }

    public function insert(
        string $groupId,
        int $postId,
        string $locale,
        int $sourcePostId,
        string $status,
        string $hash
    ): TranslationRecord {
        global $wpdb;

        $now = gmdate('Y-m-d H:i:s');
        $inserted = $wpdb->insert(
            Schema::table(),
            [
                'group_id' => $groupId,
                'post_id' => $postId,
                'locale' => $locale,
                'source_post_id' => $sourcePostId,
                'status' => $status,
                'source_hash' => $hash,
                'last_error' => '',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            $existing = $this->findByPostId($postId);
            if ($existing instanceof TranslationRecord) {
                return $existing;
            }
            throw new \RuntimeException('Could not store the translation record.');
        }

        $record = $this->findById((int) $wpdb->insert_id);
        if (! $record instanceof TranslationRecord) {
            throw new \RuntimeException('Could not read the translation record back.');
        }

        return $record;
    }

    /**
     * @return list<TranslationRecord>
     */
    public function originals(string $locale, int $page, int $perPage, string $search = ''): array
    {
        global $wpdb;

        $table = Schema::table();
        $offset = max(0, ($page - 1) * $perPage);
        $searchSql = '';
        $params = [$locale];
        if ($search !== '') {
            $searchSql = ' AND p.post_title LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        $params[] = $perPage;
        $params[] = $offset;

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT t.* FROM {$table} t
            INNER JOIN {$wpdb->posts} p ON p.ID = t.post_id
            WHERE t.locale = %s AND t.status = 'original'{$searchSql}
            ORDER BY p.post_modified_gmt DESC
            LIMIT %d OFFSET %d";

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params));
        return $this->mapRows($rows);
    }

    public function countOriginals(string $locale, string $search = ''): int
    {
        global $wpdb;

        $table = Schema::table();
        $params = [$locale];
        $searchSql = '';
        if ($search !== '') {
            $searchSql = ' AND p.post_title LIKE %s';
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT COUNT(*) FROM {$table} t
            INNER JOIN {$wpdb->posts} p ON p.ID = t.post_id
            WHERE t.locale = %s AND t.status = 'original'{$searchSql}";

        return (int) $wpdb->get_var($wpdb->prepare($sql, ...$params));
    }

    /**
     * @return list<TranslationRecord>
     */
    public function originalsAfter(string $locale, int $afterPostId, int $limit): array
    {
        global $wpdb;
        $table = Schema::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                WHERE locale = %s AND status = 'original' AND post_id > %d
                ORDER BY post_id ASC
                LIMIT %d",
                $locale,
                $afterPostId,
                $limit
            )
        );

        return $this->mapRows($rows);
    }

    /**
     * @param list<string> $groupIds
     * @return array<string, array<string, TranslationRecord>>
     */
    public function groupedByGroupIds(array $groupIds): array
    {
        if ($groupIds === []) {
            return [];
        }

        global $wpdb;
        $table = Schema::table();
        $placeholders = implode(',', array_fill(0, count($groupIds), '%s'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE group_id IN ({$placeholders})",
                ...$groupIds
            )
        );

        $grouped = [];
        foreach ($this->mapRows($rows) as $record) {
            $grouped[$record->groupId][$record->locale] = $record;
        }

        return $grouped;
    }

    public function claimById(int $id): bool
    {
        global $wpdb;

        $table = Schema::table();
        $now = gmdate('Y-m-d H:i:s');
        $stale = gmdate('Y-m-d H:i:s', time() - 600);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                SET status = 'translating', last_error = '', updated_at = %s
                WHERE id = %d AND (status != 'translating' OR updated_at < %s)",
                $now,
                $id,
                $stale
            )
        );

        return $updated === 1;
    }

    public function claimNextPending(): ?TranslationRecord
    {
        global $wpdb;

        $table = Schema::table();
        $stale = gmdate('Y-m-d H:i:s', time() - 600);

        for ($attempt = 0; $attempt < 8; $attempt++) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $row = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table}
                    WHERE status = 'pending' OR (status = 'translating' AND updated_at < %s)
                    ORDER BY updated_at ASC
                    LIMIT 1",
                    $stale
                )
            );
            if (! $row) {
                return null;
            }

            $record = TranslationRecord::fromRow($row);
            if ($this->claimById($record->id)) {
                return $this->findById($record->id);
            }
        }

        return null;
    }

    public function countPending(): int
    {
        global $wpdb;
        $table = Schema::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE status IN ('pending', 'translating')"
        );
    }

    public function countInFlight(): int
    {
        global $wpdb;
        $table = Schema::table();
        $fresh = gmdate('Y-m-d H:i:s', time() - 600);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'translating' AND updated_at >= %s",
                $fresh
            )
        );
    }

    public function countClaimable(): int
    {
        global $wpdb;
        $table = Schema::table();
        $stale = gmdate('Y-m-d H:i:s', time() - 600);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table}
                WHERE status = 'pending' OR (status = 'translating' AND updated_at < %s)",
                $stale
            )
        );
    }

    public function markPending(int $id): void
    {
        $this->update($id, [
            'status' => 'pending',
            'last_error' => '',
        ]);
    }

    public function markTranslated(int $id, string $hash): void
    {
        $this->update($id, [
            'status' => 'translated',
            'source_hash' => $hash,
            'last_error' => '',
        ]);
    }

    public function markFailed(int $id, string $error): void
    {
        $this->update($id, [
            'status' => 'failed',
            'last_error' => mb_substr($error, 0, 1000),
        ]);
    }

    public function deleteByPostId(int $postId): void
    {
        global $wpdb;
        $wpdb->delete(Schema::table(), ['post_id' => $postId], ['%d']);
    }

    /**
     * @param array<string, string> $fields
     */
    private function update(int $id, array $fields): void
    {
        global $wpdb;

        $fields['updated_at'] = gmdate('Y-m-d H:i:s');
        $formats = array_fill(0, count($fields), '%s');
        $wpdb->update(Schema::table(), $fields, ['id' => $id], $formats, ['%d']);
    }

    /**
     * @param array<int, object>|null $rows
     * @return list<TranslationRecord>
     */
    private function mapRows(?array $rows): array
    {
        if ($rows === null) {
            return [];
        }

        $records = [];
        foreach ($rows as $row) {
            $records[] = TranslationRecord::fromRow($row);
        }
        return $records;
    }
}
