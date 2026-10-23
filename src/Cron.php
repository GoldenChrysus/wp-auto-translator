<?php

declare(strict_types=1);

namespace WpAutoTranslator;

use WpAutoTranslator\Translation\TranslationProcessor;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * One cron event translates a single post, then schedules the next event for 15 seconds
 * after that job finishes. While the event runs it also asks the site to start more jobs,
 * up to WORKERS at a time. Those extra requests are separate PHP processes.
 */
final class Cron
{
    public const HOOK = 'wat_process_queue';

    public const WORKERS = 4;

    private const WORKER_ACTION = 'wat_cron_worker';

    public function __construct(private TranslationProcessor $processor, private TranslationRepository $repository)
    {
    }

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'run']);
        add_action('wp_ajax_' . self::WORKER_ACTION, [$this, 'worker']);
        add_action('wp_ajax_nopriv_' . self::WORKER_ACTION, [$this, 'worker']);
    }

    public static function scheduleSoon(): void
    {
        if (! wp_next_scheduled(self::HOOK)) {
            wp_schedule_single_event(time() + 15, self::HOOK);
        }
    }

    public function run(): void
    {
        if (! $this->canRun()) {
            return;
        }

        $slots = self::WORKERS - $this->repository->countInFlight();
        if ($slots < 1) {
            if ($this->repository->countPending() > 0) {
                self::scheduleSoon();
            }
            return;
        }

        $available = $this->repository->countClaimable();
        if ($available < 1) {
            return;
        }

        $remote = min($slots, $available) - 1;
        if ($remote > 0) {
            $this->spawn($remote);
        }

        $this->processor->processNext();
        if ($this->repository->countPending() > 0) {
            self::scheduleSoon();
        }
    }

    public function worker(): void
    {
        check_ajax_referer(self::WORKER_ACTION);

        if (! $this->canRun()) {
            wp_die('', '', ['response' => 403]);
        }

        $this->processor->processNext();
        if ($this->repository->countPending() > 0) {
            self::scheduleSoon();
        }

        wp_die();
    }

    private function canRun(): bool
    {
        return Options::isReady() && Options::apiKey() !== '';
    }

    private function spawn(int $count): void
    {
        $nonce = wp_create_nonce(self::WORKER_ACTION);
        $url = admin_url('admin-ajax.php');

        for ($i = 0; $i < $count; $i++) {
            wp_remote_post($url, [
                'timeout' => 0.01,
                'blocking' => false,
                'sslverify' => (bool) apply_filters('https_local_ssl_verify', false),
                'body' => [
                    'action' => self::WORKER_ACTION,
                    '_ajax_nonce' => $nonce,
                ],
            ]);
        }
    }
}
