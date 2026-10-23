<?php

declare(strict_types=1);

namespace WpAutoTranslator\Admin;

use WpAutoTranslator\Capabilities;
use WpAutoTranslator\LocaleRegistry;
use WpAutoTranslator\Options;
use WpAutoTranslator\TranslationRepository;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class StatusScreen
{
    private ?TranslationsListTable $table = null;

    public function __construct(
        private LocaleRegistry $locales,
        private TranslationRepository $repository,
    ) {
    }

    public function load(): void
    {
        $this->table = new TranslationsListTable($this->repository, $this->locales);
    }

    public function render(): void
    {
        if (! Capabilities::currentUserCan()) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Translations', 'wp-auto-translator') . '</h1>';
        Notice::render();

        if (! Options::isReady()) {
            echo '<p>' . esc_html__('Set a base locale before translations can be tracked.', 'wp-auto-translator') . '</p>';
            echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=wat-setup')) . '">'
                . esc_html__('Start setup', 'wp-auto-translator') . '</a></p>';
            echo '</div>';
            return;
        }

        $pending = $this->repository->countPending();
        echo '<p>' . esc_html(sprintf(
            /* translators: %d is the number of translations waiting to run. */
            _n('%d translation is queued.', '%d translations are queued.', $pending, 'wp-auto-translator'),
            $pending
        )) . '</p>';

        if (Options::autoTranslate()) {
            echo '<p class="description">' . esc_html__('New published posts are queued automatically.', 'wp-auto-translator') . '</p>';
        }

        if (Options::targetLocales() === []) {
            echo '<p>' . esc_html__('Add a target language in Settings before translating.', 'wp-auto-translator') . '</p>';
        } else {
            echo '<p><button type="button" class="button button-primary" id="wat-translate-all">'
                . esc_html__('Translate published posts that are missing a translation', 'wp-auto-translator')
                . '</button></p>';
            echo '<div id="wat-progress" class="wat-progress" role="status"></div>';
        }

        echo '<form id="wat-translate" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('wat_translate_now');
        echo '<input type="hidden" name="action" value="wat_translate_now">';
        echo '</form>';

        $table = $this->table ?? new TranslationsListTable($this->repository, $this->locales);
        $table->prepare_items();
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="wat-translations">';
        $table->search_box(__('Search posts', 'wp-auto-translator'), 'wat-search');
        $table->display();
        echo '</form>';
        echo '</div>';
    }
}
