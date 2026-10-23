<?php

declare(strict_types=1);

namespace WpAutoTranslator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpAutoTranslator\LocaleRegistry;

final class LocaleRegistryTest extends TestCase
{
    public function test_accepts_wordpress_and_bcp47_spellings(): void
    {
        $locales = new LocaleRegistry();

        $this->assertSame('en_US', $locales->normalize('en_US'));
        $this->assertSame('en_US', $locales->normalize('en-US'));
        $this->assertSame('en_US', $locales->normalize(' en-us '));
        $this->assertSame('ja', $locales->normalize('ja'));
    }

    public function test_rejects_unknown_locales(): void
    {
        $this->assertNull((new LocaleRegistry())->normalize('zz'));
        $this->assertNull((new LocaleRegistry())->normalize(''));
    }

    public function test_exposes_a_language_name_for_the_prompt(): void
    {
        $locales = new LocaleRegistry();

        $this->assertSame('Japanese', $locales->languageName('ja'));
        $this->assertSame('English (United States)', $locales->label('en_US'));
    }
}
