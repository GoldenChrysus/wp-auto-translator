<?php

declare(strict_types=1);

namespace WpAutoTranslator;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * Adds a lang query to REST collections and WP_Query.
 *
 * Without lang, translation posts are hidden from the main archives, the admin list,
 * and REST collections so a translated site does not list every locale at once.
 * Fetch a translation directly by ID, or pass lang, to see it.
 */
final class LocaleQuery
{
    public function __construct(
        private LocaleRegistry $locales,
        private TranslationRepository $repository,
    ) {
    }

    public function register(): void
    {
        add_filter('query_vars', [$this, 'queryVars']);
        add_action('pre_get_posts', [$this, 'prepareQuery']);
        add_filter('posts_clauses', [$this, 'postsClauses'], 10, 2);
        add_action('rest_api_init', [$this, 'registerRest']);
        add_action('init', [$this, 'registerPostStatus']);
    }

    /**
     * @param list<string> $vars
     * @return list<string>
     */
    public function queryVars(array $vars): array
    {
        $vars[] = 'lang';
        $vars[] = 'wat_locale';
        $vars[] = 'wat_hide_translations';
        $vars[] = 'wat_include_translations';
        return $vars;
    }

    public function registerPostStatus(): void
    {
        register_post_status('wat-pending', [
            'label' => _x('Translation pending', 'post status', 'wp-auto-translator'),
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'protected' => true,
            // translators: %s is the number of posts waiting to be translated.
            'label_count' => _n_noop(
                'Translation pending <span class="count">(%s)</span>',
                'Translation pending <span class="count">(%s)</span>',
                'wp-auto-translator'
            ),
        ]);
    }

    public function prepareQuery(\WP_Query $query): void
    {
        if ($query->get('wat_include_translations')) {
            $query->set('wat_hide_translations', false);
            return;
        }

        $lang = $query->get('lang');
        if (is_string($lang) && $lang !== '') {
            $locale = $this->locales->normalize($lang);
            if ($locale === null) {
                $query->set('post__in', [0]);
                return;
            }
            $query->set('wat_locale', $locale);
            $query->set('wat_hide_translations', false);
            return;
        }

        if ($query->get('wat_locale')) {
            return;
        }

        if ($this->isSpecificPost($query)) {
            return;
        }

        if (is_admin()) {
            if ($query->is_main_query() && $this->isTranslatableAdminList($query)) {
                $query->set('wat_hide_translations', true);
            }
            return;
        }

        if ($query->is_main_query()) {
            $query->set('wat_hide_translations', true);
        }
    }

    public function registerRest(): void
    {
        foreach (PostTypes::all() as $postType) {
            add_filter("rest_{$postType}_query", [$this, 'filterRestQuery'], 10, 2);
            add_filter("rest_{$postType}_collection_params", [$this, 'collectionParams']);
            register_rest_field($postType, 'lang', [
                'get_callback' => [$this, 'restLang'],
                'schema' => [
                    'description' => __('Locale of this post.', 'wp-auto-translator'),
                    'type' => ['string', 'null'],
                    'context' => ['view', 'edit'],
                ],
            ]);
        }
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function filterRestQuery(array $args, \WP_REST_Request $request): array
    {
        $lang = $request->get_param('lang');
        if (is_string($lang) && $lang !== '') {
            $locale = $this->locales->normalize($lang);
            if ($locale !== null) {
                $args['wat_locale'] = $locale;
                $args['wat_hide_translations'] = false;
            }
            return $args;
        }

        $args['wat_hide_translations'] = true;
        return $args;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function collectionParams(array $params): array
    {
        $params['lang'] = [
            'description' => __('Limit results to a locale, for example ja or en_US.', 'wp-auto-translator'),
            'type' => 'string',
            'validate_callback' => function ($value): bool|\WP_Error {
                if ($value === null || $value === '') {
                    return true;
                }
                if (! is_string($value) || $this->locales->normalize($value) === null) {
                    return new \WP_Error(
                        'wat_invalid_locale',
                        __('Unknown locale. Use a locale this plugin knows, such as ja or en_US.', 'wp-auto-translator'),
                        ['status' => 400]
                    );
                }
                return true;
            },
        ];

        return $params;
    }

    /**
     * @param array<string, mixed> $post
     */
    public function restLang(array $post): ?string
    {
        $postId = isset($post['id']) ? (int) $post['id'] : 0;
        if ($postId <= 0) {
            return null;
        }

        $record = $this->repository->findByPostId($postId);
        return $record instanceof TranslationRecord ? $record->locale : null;
    }

    /**
     * @param array<string, string> $clauses
     * @return array<string, string>
     */
    public function postsClauses(array $clauses, \WP_Query $query): array
    {
        global $wpdb;

        $table = Schema::table();
        if (preg_match('/^[A-Za-z0-9_]+$/', $table) !== 1) {
            return $clauses;
        }

        $locale = $query->get('wat_locale');
        if (is_string($locale) && $locale !== '') {
            $clauses['join'] .= $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a prefixed constant validated above.
                " INNER JOIN {$table} AS wat_tr ON wat_tr.post_id = {$wpdb->posts}.ID AND wat_tr.locale = %s ",
                $locale
            );
            return $clauses;
        }

        if ($query->get('wat_hide_translations')) {
            $clauses['join'] .= " LEFT JOIN {$table} AS wat_hide ON wat_hide.post_id = {$wpdb->posts}.ID ";
            $clauses['where'] .= ' AND (wat_hide.id IS NULL OR wat_hide.source_post_id = 0) ';
        }

        return $clauses;
    }

    private function isSpecificPost(\WP_Query $query): bool
    {
        return (bool) ($query->get('p') || $query->get('page_id') || $query->get('name') || $query->is_singular());
    }

    private function isTranslatableAdminList(\WP_Query $query): bool
    {
        $postType = $query->get('post_type');
        if ($postType === '' || $postType === null) {
            $postType = 'post';
        }
        if (is_array($postType)) {
            return false;
        }

        return in_array((string) $postType, PostTypes::all(), true);
    }
}
