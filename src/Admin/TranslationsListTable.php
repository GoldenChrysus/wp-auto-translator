<?php

declare(strict_types=1);

namespace WpAutoTranslator\Admin;

use WpAutoTranslator\ContentHasher;
use WpAutoTranslator\LocaleRegistry;
use WpAutoTranslator\Options;
use WpAutoTranslator\TranslationRecord;
use WpAutoTranslator\TranslationRepository;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

if (defined('ABSPATH') && ! class_exists(\WP_List_Table::class)) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class TranslationsListTable extends \WP_List_Table
{
    /**
     * @var array<string, array<string, TranslationRecord>>
     */
    private array $siblings = [];

    public function __construct(
        private TranslationRepository $repository,
        private LocaleRegistry $locales,
    ) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        parent::__construct([
            'singular' => 'translation',
            'plural' => 'translations',
            'ajax' => false,
            'screen' => $screen instanceof \WP_Screen ? $screen : null,
        ]);
    }

    public function prepare_items(): void
    {
        $perPage = 20;
        $search = '';
        // The search box is a read filter on a capability-protected screen.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_REQUEST['s'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $search = sanitize_text_field(wp_unslash((string) $_REQUEST['s']));
        }

        $locale = Options::baseLocale();
        $page = $this->get_pagenum();
        $this->items = $locale === '' ? [] : $this->repository->originals($locale, $page, $perPage, $search);
        $total = $locale === '' ? 0 : $this->repository->countOriginals($locale, $search);

        $groupIds = [];
        foreach ($this->items as $item) {
            if ($item instanceof TranslationRecord) {
                $groupIds[] = $item->groupId;
            }
        }
        $this->siblings = $this->repository->groupedByGroupIds($groupIds);

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page' => $perPage,
        ]);

        $columns = $this->get_columns();
        $hidden = $this->screen instanceof \WP_Screen ? get_hidden_columns($this->screen) : [];
        $this->_column_headers = [
            $columns,
            is_array($hidden) ? $hidden : [],
            [],
            isset($columns['title']) ? 'title' : (string) array_key_first($columns),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function get_columns(): array
    {
        $columns = [
            'title' => __('Post', 'wp-auto-translator'),
        ];
        foreach (Options::targetLocales() as $locale) {
            $columns['locale_' . $locale] = $this->locales->label($locale);
        }

        return $columns;
    }

    /**
     * @param TranslationRecord $item
     */
    public function column_title($item): string
    {
        $post = get_post($item->postId);
        if (! $post instanceof \WP_Post) {
            return '#' . (int) $item->postId;
        }

        $title = $post->post_title !== '' ? $post->post_title : __('(no title)', 'wp-auto-translator');
        $edit = get_edit_post_link($post);
        $html = esc_html($title);
        if (is_string($edit) && $edit !== '') {
            $html = '<a href="' . esc_url($edit) . '">' . $html . '</a>';
        }

        $statusObject = get_post_status_object($post->post_status);
        $status = is_object($statusObject) && isset($statusObject->label)
            ? (string) $statusObject->label
            : $post->post_status;

        return $html . '<br><span class="description">' . esc_html($post->post_type . ' · ' . $status) . '</span>';
    }

    /**
     * @param TranslationRecord $item
     */
    public function column_default($item, $column_name): string
    {
        if (! is_string($column_name) || ! str_starts_with($column_name, 'locale_')) {
            return '';
        }

        return $this->renderLocaleCell($item, substr($column_name, strlen('locale_')));
    }

    public function no_items(): void
    {
        esc_html_e('No base-language posts yet.', 'wp-auto-translator');
    }

    private function renderLocaleCell(TranslationRecord $original, string $locale): string
    {
        $post = get_post($original->postId);
        $hash = $post instanceof \WP_Post
            ? ContentHasher::hash($post->post_title, $post->post_content, $post->post_excerpt)
            : '';
        $sibling = $this->siblings[$original->groupId][$locale] ?? null;

        if (! $sibling instanceof TranslationRecord) {
            $badge = $this->badge('missing', __('Not translated', 'wp-auto-translator'));
            $action = __('Translate', 'wp-auto-translator');
        } elseif ($sibling->status === 'translated' && $hash !== '' && $sibling->sourceHash !== $hash) {
            $badge = $this->badge('outdated', __('Out of date', 'wp-auto-translator'));
            $action = __('Retranslate', 'wp-auto-translator');
        } else {
            $badge = $this->badge($sibling->status, $this->statusLabel($sibling->status));
            $action = $sibling->status === 'translated'
                ? __('Retranslate', 'wp-auto-translator')
                : __('Translate', 'wp-auto-translator');
        }

        $button = '<button type="submit" class="button button-small" form="wat-translate" name="wat_job" value="'
            . esc_attr($original->postId . '|' . $locale)
            . '">' . esc_html($action) . '</button>';

        $extra = '';
        if ($sibling instanceof TranslationRecord) {
            $edit = get_edit_post_link($sibling->postId);
            if (is_string($edit) && $edit !== '') {
                $extra .= ' <a href="' . esc_url($edit) . '">' . esc_html__('Edit', 'wp-auto-translator') . '</a>';
            }
            if ($sibling->status === 'failed' && $sibling->lastError !== '') {
                $extra .= '<div class="wat-error">' . esc_html($sibling->lastError) . '</div>';
            }
        }

        return $badge . ' ' . $button . $extra;
    }

    private function badge(string $status, string $label): string
    {
        return '<span class="wat-badge wat-badge-' . esc_attr($status) . '">' . esc_html($label) . '</span>';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pending' => __('Queued', 'wp-auto-translator'),
            'translating' => __('Translating', 'wp-auto-translator'),
            'translated' => __('Translated', 'wp-auto-translator'),
            'failed' => __('Failed', 'wp-auto-translator'),
            default => $status,
        };
    }
}
