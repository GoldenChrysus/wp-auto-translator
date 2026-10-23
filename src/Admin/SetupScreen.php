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

final class SetupScreen
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

        check_admin_referer('wat_setup');

        if (isset($_POST['wat_back'])) {
            update_option(Options::SETUP_STEP, (string) max(1, Options::setupStep() - 1));
            $this->redirect();
        }

        $step = isset($_POST['step']) ? (int) $_POST['step'] : 0;
        if ($step !== Options::setupStep()) {
            Notice::flash('error', __('The setup form was out of date. Review this step and submit it again.', 'wp-auto-translator'));
            $this->redirect();
        }

        match ($step) {
            1 => $this->saveBaseLocale(),
            2 => $this->saveApiKey(),
            3 => $this->saveTargets(),
            4 => $this->saveAuto(),
            5 => $this->finish(),
            default => null,
        };

        $this->redirect();
    }

    public function render(): void
    {
        if (! current_user_can(\WpAutoTranslator\Capabilities::required())) {
            return;
        }

        $step = Options::setupStep();
        echo '<div class="wrap wat-setup">';
        echo '<h1>' . esc_html__('Set up translations', 'wp-auto-translator') . '</h1>';
        $this->steps($step);
        Notice::render();

        echo '<form method="post" action="' . esc_url(admin_url('admin.php?page=wat-setup')) . '">';
        wp_nonce_field('wat_setup');
        echo '<input type="hidden" name="step" value="' . esc_attr((string) $step) . '">';

        match ($step) {
            1 => $this->renderBaseLocale(),
            2 => $this->renderApiKey(),
            3 => $this->renderTargets(),
            4 => $this->renderAuto(),
            default => $this->renderExisting(),
        };

        echo '</form>';
        echo '</div>';
    }

    private function saveBaseLocale(): void
    {
        check_admin_referer('wat_setup');
        $raw = isset($_POST['base_locale']) ? sanitize_text_field(wp_unslash((string) $_POST['base_locale'])) : '';
        $locale = $this->sanitizer->locale($raw);
        if ($locale === null) {
            Notice::flash('error', __('Choose a base locale. The plugin does nothing until this is set.', 'wp-auto-translator'));
            return;
        }

        update_option(Options::BASE_LOCALE, $locale);
        $count = $this->associator->associateExisting($locale);
        set_transient('wat_associated_count', $count, 15 * MINUTE_IN_SECONDS);
        update_option(Options::SETUP_STEP, '2');
    }

    private function saveApiKey(): void
    {
        check_admin_referer('wat_setup');
        if (! isset($_POST['wat_skip_key'])) {
            $raw = isset($_POST['api_key']) ? sanitize_text_field(wp_unslash((string) $_POST['api_key'])) : '';
            try {
                $key = $this->sanitizer->apiKey($raw);
            } catch (\InvalidArgumentException $exception) {
                Notice::flash('error', $exception->getMessage());
                return;
            }

            if ($key === '' && Options::apiKey() === '') {
                Notice::flash('error', __('Paste an API key, or skip this step and add one later in Settings.', 'wp-auto-translator'));
                return;
            }
            if ($key !== '') {
                update_option(Options::API_KEY, $key, false);
            }
        }

        update_option(Options::SETUP_STEP, '3');
    }

    private function saveTargets(): void
    {
        check_admin_referer('wat_setup');
        $raw = isset($_POST['targets']) && is_array($_POST['targets'])
            ? array_map('sanitize_text_field', wp_unslash($_POST['targets']))
            : [];
        $targets = $this->sanitizer->targetLocales($raw, Options::baseLocale());
        update_option(Options::TARGET_LOCALES, $targets);
        update_option(Options::SETUP_STEP, '4');
        if ($targets === []) {
            Notice::flash('warning', __('No target languages were selected. You can add them later in Settings.', 'wp-auto-translator'));
        }
    }

    private function saveAuto(): void
    {
        check_admin_referer('wat_setup');
        $choice = isset($_POST['auto_translate']) ? sanitize_text_field(wp_unslash((string) $_POST['auto_translate'])) : '0';
        $enabled = $choice === '1';
        update_option(Options::AUTO_TRANSLATE, $enabled ? '1' : '0');
        update_option(Options::SETUP_STEP, '5');
    }

    private function finish(): void
    {
        update_option(Options::SETUP_COMPLETE, '1');
        Notice::flash('success', __('Translation setup is complete.', 'wp-auto-translator'));
        wp_safe_redirect(admin_url('admin.php?page=wat-translations'));
        exit;
    }

    private function renderBaseLocale(): void
    {
        echo '<p>' . esc_html__('Which language are the posts on this site written in? Nothing is translated until you answer this.', 'wp-auto-translator') . '</p>';
        echo '<table class="form-table"><tr><th scope="row"><label for="base_locale">' . esc_html__('Base locale', 'wp-auto-translator') . '</label></th><td>';
        Fields::localeSelect('base_locale', Options::baseLocale(), $this->locales);
        echo '</td></tr></table>';
        $this->submit(__('Save and tag existing posts', 'wp-auto-translator'));
    }

    private function renderApiKey(): void
    {
        $count = get_transient('wat_associated_count');
        if (is_numeric($count)) {
            echo '<p>' . esc_html(sprintf(
                /* translators: %d is the number of posts tagged with the base locale. */
                _n(
                    'Tagged %d existing post with the base locale.',
                    'Tagged %d existing posts with the base locale.',
                    (int) $count,
                    'wp-auto-translator'
                ),
                (int) $count
            )) . '</p>';
        }

        echo '<p>' . esc_html__('Translations are requested from OpenAI. You can also define WAT_OPENAI_API_KEY in wp-config.php.', 'wp-auto-translator') . '</p>';
        echo '<table class="form-table"><tr><th scope="row"><label for="api_key">' . esc_html__('API key', 'wp-auto-translator') . '</label></th><td>';
        echo '<input name="api_key" id="api_key" type="password" class="regular-text" autocomplete="off" placeholder="' . esc_attr__('sk-...', 'wp-auto-translator') . '">';
        echo '</td></tr></table>';
        $this->submit(__('Save API key', 'wp-auto-translator'));
        echo ' <button type="submit" class="button" name="wat_skip_key" value="1">' . esc_html__('Skip for now', 'wp-auto-translator') . '</button>';
        $this->back();
    }

    private function renderTargets(): void
    {
        echo '<p>' . esc_html__('Which languages should new and existing posts be translated into?', 'wp-auto-translator') . '</p>';
        Fields::localeChecklist('targets', Options::targetLocales(), Options::baseLocale(), $this->locales);
        $this->submit(__('Save languages', 'wp-auto-translator'));
        $this->back();
    }

    private function renderAuto(): void
    {
        echo '<p>' . esc_html__('When a base-language post is published, queue a translation for every target language. Drafts are not sent to the model until they are published.', 'wp-auto-translator') . '</p>';
        echo '<p>';
        echo '<label><input type="radio" name="auto_translate" value="1"';
        checked(Options::autoTranslate());
        echo '> ' . esc_html__('Yes, translate new posts automatically', 'wp-auto-translator') . '</label><br>';
        echo '<label><input type="radio" name="auto_translate" value="0"';
        checked(! Options::autoTranslate());
        echo '> ' . esc_html__('No, I will start translations myself', 'wp-auto-translator') . '</label>';
        echo '</p>';
        $this->submit(__('Continue', 'wp-auto-translator'));
        $this->back();
    }

    private function renderExisting(): void
    {
        echo '<p>' . esc_html__('Published posts in the base language can be translated now. Each language is one model request, and the queue keeps going if you leave this page.', 'wp-auto-translator') . '</p>';
        echo '<p><button type="button" class="button button-primary" id="wat-translate-all">' . esc_html__('Translate all published posts', 'wp-auto-translator') . '</button></p>';
        echo '<div id="wat-progress" class="wat-progress" role="status"></div>';
        $this->submit(__('Finish setup', 'wp-auto-translator'));
    }

    private function steps(int $current): void
    {
        $labels = [
            1 => __('Base locale', 'wp-auto-translator'),
            2 => __('API key', 'wp-auto-translator'),
            3 => __('Languages', 'wp-auto-translator'),
            4 => __('New posts', 'wp-auto-translator'),
            5 => __('Existing posts', 'wp-auto-translator'),
        ];
        echo '<ol class="wat-steps">';
        foreach ($labels as $number => $label) {
            $className = $number === $current ? 'is-current' : '';
            echo '<li class="' . esc_attr($className) . '">' . esc_html($label) . '</li>';
        }
        echo '</ol>';
    }

    private function submit(string $label): void
    {
        echo '<p class="wat-actions"><button type="submit" class="button button-primary">' . esc_html($label) . '</button></p>';
    }

    private function back(): void
    {
        echo ' <button type="submit" class="button" name="wat_back" value="1">' . esc_html__('Back', 'wp-auto-translator') . '</button>';
    }

    private function isPost(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This only chooses which screen handles an already-nonced form.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : '';
        $method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REQUEST_METHOD'])) : '';

        return $page === 'wat-setup' && $method === 'POST';
    }

    private function redirect(): void
    {
        wp_safe_redirect(admin_url('admin.php?page=wat-setup'));
        exit;
    }
}
