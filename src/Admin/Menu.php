<?php

declare(strict_types=1);

namespace WpAutoTranslator\Admin;

use WpAutoTranslator\Capabilities;
use WpAutoTranslator\LocaleRegistry;
use WpAutoTranslator\Options;
use WpAutoTranslator\PostAssociator;
use WpAutoTranslator\SettingsSanitizer;
use WpAutoTranslator\TranslationRepository;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

final class Menu
{
    private SetupScreen $setup;

    private SettingsScreen $settings;

    private StatusScreen $status;

    public function __construct(
        LocaleRegistry $locales,
        TranslationRepository $repository,
    ) {
        $sanitizer = new SettingsSanitizer($locales);
        $associator = new PostAssociator($repository);
        $this->setup = new SetupScreen($locales, $sanitizer, $associator);
        $this->settings = new SettingsScreen($locales, $sanitizer, $associator);
        $this->status = new StatusScreen($locales, $repository);
    }

    public function register(): void
    {
        $capability = Capabilities::required();
        $hook = add_menu_page(
            __('Translations', 'wp-auto-translator'),
            __('Translations', 'wp-auto-translator'),
            $capability,
            'wat-translations',
            [$this->status, 'render'],
            'dashicons-translation',
            58
        );
        if (is_string($hook) && $hook !== '') {
            // WordPress caches list-table columns while rendering the admin header.
            // The table has to exist before that, or the cache stays empty and the
            // rows render with no columns.
            add_action('load-' . $hook, [$this->status, 'load']);
        }
        add_submenu_page(
            'wat-translations',
            __('Status', 'wp-auto-translator'),
            __('Status', 'wp-auto-translator'),
            $capability,
            'wat-translations',
            [$this->status, 'render']
        );
        add_submenu_page(
            'wat-translations',
            __('Settings', 'wp-auto-translator'),
            __('Settings', 'wp-auto-translator'),
            $capability,
            'wat-settings',
            [$this->settings, 'render']
        );
        add_submenu_page(
            'wat-translations',
            __('Setup', 'wp-auto-translator'),
            __('Setup', 'wp-auto-translator'),
            $capability,
            'wat-setup',
            [$this->setup, 'render']
        );

        if (Options::setupComplete()) {
            remove_submenu_page('wat-translations', 'wat-setup');
        }
    }

    public function handleRequests(): void
    {
        $this->maybeRedirectAfterActivation();
        if (! Capabilities::currentUserCan()) {
            return;
        }
        $this->setup->handlePost();
        $this->settings->handlePost();
    }

    public function notices(): void
    {
        if (! Capabilities::currentUserCan()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing, not a form submission.
        $page = isset($_GET['page']) ? sanitize_key((string) wp_unslash($_GET['page'])) : '';

        if (! Options::isReady() && $page !== 'wat-setup') {
            $link = '<a href="' . esc_url(admin_url('admin.php?page=wat-setup')) . '">'
                . esc_html__('Finish setup', 'wp-auto-translator') . '</a>';
            wp_admin_notice(
                sprintf(
                    /* translators: %s is a link to the setup screen. */
                    __('WP Auto Translator is idle until you set a base locale. %s', 'wp-auto-translator'),
                    $link
                ),
                ['type' => 'warning']
            );
        }

        if (Options::setupComplete() && Options::apiKey() === '' && in_array($page, ['wat-translations', 'wat-settings'], true)) {
            wp_admin_notice(
                esc_html__('Translations cannot run until an OpenAI API key is saved.', 'wp-auto-translator'),
                ['type' => 'warning']
            );
        }
    }

    public function assets(string $hook): void
    {
        if (! str_contains($hook, 'wat-')) {
            return;
        }

        wp_enqueue_style('wat-admin', plugins_url('assets/admin.css', WAT_FILE), [], WAT_VERSION);
        wp_enqueue_script('wat-admin', plugins_url('assets/admin.js', WAT_FILE), [], WAT_VERSION, true);
        wp_localize_script('wat-admin', 'watAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wat_admin'),
        ]);
    }

    private function maybeRedirectAfterActivation(): void
    {
        if (! get_transient('wat_activation_redirect') || ! Capabilities::currentUserCan()) {
            return;
        }

        delete_transient('wat_activation_redirect');
        if (wp_doing_ajax() || Options::isReady()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (isset($_GET['activate-multi'])) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=wat-setup'));
        exit;
    }
}
