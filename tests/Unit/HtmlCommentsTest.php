<?php

declare(strict_types=1);

namespace WpAutoTranslator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpAutoTranslator\HtmlComments;

final class HtmlCommentsTest extends TestCase
{
    public function test_block_comments_survive_a_sanitizer_that_strips_comments_and_scripts(): void
    {
        $html = "<!-- wp:paragraph -->\n<p>Hello</p>\n<!-- /wp:paragraph --><script>alert(1)</script>";

        $clean = HtmlComments::preserve($html, static function (string $value): string {
            $value = preg_replace('/<!--.*?-->/s', '', $value) ?? $value;
            return preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $value) ?? $value;
        });

        $this->assertStringContainsString('<!-- wp:paragraph -->', $clean);
        $this->assertStringContainsString('<!-- /wp:paragraph -->', $clean);
        $this->assertStringContainsString('<p>Hello</p>', $clean);
        $this->assertStringNotContainsString('<script>', $clean);
    }
}
