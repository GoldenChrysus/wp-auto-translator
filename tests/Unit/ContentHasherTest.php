<?php

declare(strict_types=1);

namespace WpAutoTranslator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpAutoTranslator\ContentHasher;

final class ContentHasherTest extends TestCase
{
    public function test_hash_changes_when_any_translated_field_changes(): void
    {
        $original = ContentHasher::hash('Title', '<p>Body</p>', 'Excerpt');

        $this->assertSame($original, ContentHasher::hash('Title', '<p>Body</p>', 'Excerpt'));
        $this->assertNotSame($original, ContentHasher::hash('Title!', '<p>Body</p>', 'Excerpt'));
        $this->assertNotSame($original, ContentHasher::hash('Title', '<p>Body</p> ', 'Excerpt'));
        $this->assertNotSame($original, ContentHasher::hash('Title', '<p>Body</p>', ''));
    }
}
