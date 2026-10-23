<?php

declare(strict_types=1);

namespace WpAutoTranslator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WpAutoTranslator\Translation\ResponseParser;
use WpAutoTranslator\Translation\TranslationException;

final class ResponseParserTest extends TestCase
{
    private ResponseParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ResponseParser();
    }

    public function test_parses_json_and_fenced_json(): void
    {
        $payload = $this->parser->parse('{"title":"Hola","content":"<p>Hola</p>","excerpt":"Resumen"}');
        $this->assertSame('Hola', $payload->title);
        $this->assertSame('<p>Hola</p>', $payload->content);

        $fenced = $this->parser->parse("```json\n{\"title\":\"Hola\",\"content\":\"<p>Hola</p>\",\"excerpt\":\"\"}\n```");
        $this->assertSame('Hola', $fenced->title);
        $this->assertSame('', $fenced->excerpt);
    }

    public function test_rejects_a_missing_title(): void
    {
        $this->expectException(TranslationException::class);
        $this->parser->parse('{"content":"<p>Hola</p>","excerpt":""}');
    }

    public function test_rejects_text_that_is_not_json(): void
    {
        $this->expectException(TranslationException::class);
        $this->parser->parse('Here is the translation.');
    }
}
