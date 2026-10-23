<?php

declare(strict_types=1);

namespace WpAutoTranslator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpAutoTranslator\LocaleRegistry;
use WpAutoTranslator\SettingsSanitizer;

final class SettingsSanitizerTest extends TestCase
{
    private SettingsSanitizer $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new SettingsSanitizer(new LocaleRegistry());
    }

    public function test_target_locales_drop_the_base_locale_and_unknown_codes(): void
    {
        $targets = $this->sanitizer->targetLocales(['ja', 'en-US', 'zz', 'fr_FR', 'ja'], 'en_US');

        $this->assertSame(['ja', 'fr_FR'], $targets);
    }

    public function test_model_falls_back_when_the_id_is_unsafe(): void
    {
        $this->assertSame('gpt-4.1-mini', $this->sanitizer->model('gpt-4.1-mini'));
        $this->assertSame('gpt-6-luna', $this->sanitizer->model('gpt 4'));
        $this->assertSame('gpt-6-luna', $this->sanitizer->model(''));
    }

    public function test_api_key_must_be_a_single_token(): void
    {
        $this->assertSame('', $this->sanitizer->apiKey('  '));
        $this->assertSame('sk-test-key-1234567890', $this->sanitizer->apiKey(' sk-test-key-1234567890 '));

        $this->expectException(\InvalidArgumentException::class);
        $this->sanitizer->apiKey("sk-test-key-1234567890\ninjected");
    }
}
