<?php

declare(strict_types=1);

namespace WpAutoTranslator;

use WpAutoTranslator\Translation\TranslationProcessor;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * Schedules a single follow-up event after each job so an empty site does not wake every minute.
 */
final class Cron
{
    public const HOOK = 'wat_process_queue';

    public function __construct(private TranslationProcessor $processor, private TranslationRepository $repository)
    {
    }

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'run']);
    }

    public static function scheduleSoon(): void
    {
        if (! wp_next_scheduled(self::HOOK)) {
            wp_schedule_single_event(time() + 15, self::HOOK);
        }
    }

    public function run(): void
    {
        if (! Options::isReady() || Options::apiKey() === '') {
            return;
        }

        $this->processor->processNext();
        if ($this->repository->countPending() > 0) {
            self::scheduleSoon();
        }
    }
}
