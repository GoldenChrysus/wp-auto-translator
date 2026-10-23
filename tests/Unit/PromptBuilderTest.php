<?php

declare(strict_types=1);

namespace WpAutoTranslator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpAutoTranslator\Translation\PromptBuilder;
use WpAutoTranslator\Translation\TranslationPayload;

final class PromptBuilderTest extends TestCase
{
    public function test_prompt_names_both_locales_and_preserves_block_markup(): void
    {
        $builder = new PromptBuilder();
        $system = $builder->system('en_US', 'English', 'ja', 'Japanese');

        $this->assertStringContainsString('en_US', $system);
        $this->assertStringContainsString('Japanese', $system);
        $this->assertStringContainsString('Gutenberg', $system);
        $this->assertStringContainsString('shortcodes', $system);

        $user = $builder->user(new TranslationPayload('Hello', "<!-- wp:paragraph -->\n<p>Hi</p>", ''));
        $this->assertStringContainsString('<!-- wp:paragraph -->', $user);
        $this->assertStringContainsString('json', $user);
        $decoded = json_decode(substr($user, strlen("Translate this post to json:\n")), true);
        $this->assertSame('Hello', $decoded['title']);
    }
}
