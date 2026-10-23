<?php

declare(strict_types=1);

namespace WpAutoTranslator\Admin;

use WpAutoTranslator\LocaleRegistry;
use WpAutoTranslator\Options;
use WpAutoTranslator\PostAssociator;
use WpAutoTranslator\SettingsSanitizer;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class SettingsScreen
{
    public function __construct(
        private LocaleRegistry $locales,
        private SettingsSanitizer $sanitizer,
        private PostAssociator $associator,
    ) {
    }

    public function handlePost(): void
    {
        if (! $this->isPost()) {
            return;
        }

        check_admin_referer('wat_settings');

        if (isset($_POST['associate_missing'])) {
            $locale = Options::baseLocale();
            if ($locale === '') {
                Notice::flash('error', __('Set a base locale before tagging posts.', 'wp-auto-translator'));
                $this->redirect();
            }
            $count = $this->associator->associateExisting($locale);
            Notice::flash('success', sprintf(
                /* translators: %d is the number of posts newly tagged with the base locale. */
                _n('Tagged %d post.', 'Tagged %d posts.', $count, 'wp-auto-translator'),
                $count
            ));
            $this->redirect();
        }

        $this->save();
        $this->redirect();
    }

    public function render(): void
    {
        if (! current_user_can(\WpAutoTranslator\Capabilities::required())) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Translation settings', 'wp-auto-translator') . '</h1>';
        Notice::render();

        if (! Options::isReady()) {
            echo '<p>' . esc_html__('The plugin is idle until a base locale is saved.', 'wp-auto-translator') . '</p>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=wat-settings')) . '">';
        wp_nonce_field('wat_settings');
        echo '<table class="form-table">';

        echo '<tr><th scope="row"><label for="base_locale">' . esc_html__('Base locale', 'wp-auto-translator') . '</label></th><td>';
        Fields::localeSelect('base_locale', Options::baseLocale(), $this->locales);
        echo '<p class="description">' . esc_html__('Posts already tagged keep their locale. New posts use the locale you save here.', 'wp-auto-translator') . '</p>';
        echo '<label><input type="checkbox" name="confirm_base_change" value="1"> ' . esc_html__('I am deliberately changing the base locale.', 'wp-auto-translator') . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="api_key">' . esc_html__('OpenAI API key', 'wp-auto-translator') . '</label></th><td>';
        if (Options::apiKeyIsConstant()) {
            echo '<p>' . esc_html__('The key is defined by WAT_OPENAI_API_KEY in wp-config.php.', 'wp-auto-translator') . '</p>';
        } else {
            $placeholder = Options::apiKey() !== ''
                ? __('A key is saved. Leave this blank to keep it.', 'wp-auto-translator')
                : 'sk-...';
            echo '<input name="api_key" id="api_key" type="password" class="regular-text" autocomplete="off" placeholder="' . esc_attr($placeholder) . '">';
            echo '<br><label><input type="checkbox" name="clear_api_key" value="1"> ' . esc_html__('Remove the saved key', 'wp-auto-translator') . '</label>';
        }
        echo '<p><button type="button" class="button" id="wat-test-api">' . esc_html__('Test connection', 'wp-auto-translator') . '</button> <span id="wat-test-result" role="status"></span></p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="model">' . esc_html__('Model', 'wp-auto-translator') . '</label></th><td>';
        echo '<input name="model" id="model" type="text" class="regular-text" value="' . esc_attr(Options::model()) . '">';
        echo '<p class="description">' . esc_html__('Any OpenAI model id, such as gpt-6-luna.', 'wp-auto-translator') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Target languages', 'wp-auto-translator') . '</th><td>';
        Fields::localeChecklist('targets', Options::targetLocales(), Options::baseLocale(), $this->locales);
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('New posts', 'wp-auto-translator') . '</th><td>';
        echo '<label><input type="checkbox" name="auto_translate" value="1"';
        checked(Options::autoTranslate());
        echo '> ' . esc_html__('Automatically queue translations when a base-language post is published', 'wp-auto-translator') . '</label>';
        echo '</td></tr>';

        echo '</table>';
        submit_button(__('Save settings', 'wp-auto-translator'));
        echo '</form>';

        echo '<hr><h2>' . esc_html__('Posts with no locale', 'wp-auto-translator') . '</h2>';
        echo '<p>' . esc_html__('Use this if posts were created before the base locale was set.', 'wp-auto-translator') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=wat-settings')) . '">';
        wp_nonce_field('wat_settings');
        echo '<button type="submit" class="button" name="associate_missing" value="1">' . esc_html__('Tag unassigned posts with the base locale', 'wp-auto-translator') . '</button>';
        echo '</form>';
        echo '</div>';
    }

    private function save(): void
    {
        check_admin_referer('wat_settings');
        $rawLocale = isset($_POST['base_locale']) ? sanitize_text_field(wp_unslash((string) $_POST['base_locale'])) : '';
        $locale = $this->sanitizer->locale($rawLocale);
        if ($locale === null) {
            Notice::flash('error', __('Choose a valid base locale.', 'wp-auto-translator'));
            return;
        }

        $key = null;
        $clearKey = false;
        if (! Options::apiKeyIsConstant()) {
            $clearKey = isset($_POST['clear_api_key']);
            if (! $clearKey) {
                $rawKey = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash((string) $_POST['api_key'])) : '';
                try {
                    $key = $this->sanitizer->apiKey($rawKey);
                } catch (\InvalidArgumentException $exception) {
                    Notice::flash('error', $exception->getMessage());
                    return;
                }
            }
        }

        $baseNote = '';
        if ($locale !== Options::baseLocale()) {
            if (Options::baseLocale() !== '' && ! isset($_POST['confirm_base_change'])) {
                $baseNote = ' ' . __('Base locale was left unchanged. Check the confirmation box to change it.', 'wp-auto-translator');
            } else {
                update_option(Options::BASE_LOCALE, $locale);
            }
        }

        if ($clearKey) {
            update_option(Options::API_KEY, '', false);
        } elseif (is_string($key) && $key !== '') {
            update_option(Options::API_KEY, $key, false);
        }

        $model = isset($_POST['model']) ? sanitize_text_field(wp_unslash((string) $_POST['model'])) : '';
        update_option(Options::MODEL, $this->sanitizer->model($model));

        $rawTargets = isset($_POST['targets']) && is_array($_POST['targets'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['targets']))
            : [];
        update_option(Options::TARGET_LOCALES, $this->sanitizer->targetLocales($rawTargets, Options::baseLocale()));
        update_option(Options::AUTO_TRANSLATE, isset($_POST['auto_translate']) ? '1' : '0');
        update_option(Options::SETUP_COMPLETE, '1');

        Notice::flash(
            $baseNote === '' ? 'success' : 'warning',
            __('Settings saved.', 'wp-auto-translator') . $baseNote
        );
    }

    private function isPost(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This only chooses which screen handles an already-nonced form.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD'])) : '';

        return $page === 'wat-settings' && $method === 'POST';
    }

    private function redirect(): void
    {
        wp_safe_redirect(admin_url('admin.php?page=wat-settings'));
        exit;
    }
}
