<?php

declare(strict_types=1);

namespace WpAutoTranslator\Tests\Unit;

use NeuronAI\HttpClient\HttpResponse;
use PHPUnit\Framework\TestCase;
use WpAutoTranslator\Tests\Support\FakeNeuronHttpClient;
use WpAutoTranslator\Translation\OpenAiTranslator;
use WpAutoTranslator\Translation\TranslationException;
use WpAutoTranslator\Translation\TranslationPayload;

final class OpenAiTranslatorTest extends TestCase
{
    public function test_sends_a_structured_responses_request_without_temperature(): void
    {
        $client = new FakeNeuronHttpClient(new HttpResponse(200, $this->envelope($this->translationJson())));
        $translator = new OpenAiTranslator('sk-test-key-1234567890', 'gpt-6-luna', $client);

        $result = $translator->translate(
            'en_US',
            'English',
            'fr_FR',
            'French',
            new TranslationPayload('Hello', '<p>Hello</p>', 'Hi')
        );

        $this->assertSame('Bonjour', $result->title);
        $this->assertCount(1, $client->requests);
        $request = $client->requests[0];
        $body = $request->body;
        $this->assertIsArray($body);
        $this->assertSame('https://api.openai.com/v1/responses', $request->uri);
        $this->assertSame('Bearer sk-test-key-1234567890', $request->headers['Authorization']);
        $this->assertSame('gpt-6-luna', $body['model']);
        $this->assertSame('json_schema', $body['text']['format']['type']);
        $this->assertFalse($body['store']);
        $this->assertArrayNotHasKey('temperature', $body);
        $this->assertStringContainsString('json', (string) json_encode($body['input']));
        $this->assertStringNotContainsString('sk-test-key-1234567890', (string) json_encode($body));
    }

    public function test_api_errors_do_not_echo_the_key(): void
    {
        $client = new FakeNeuronHttpClient(new HttpResponse(401, json_encode([
            'error' => ['message' => 'Incorrect API key sk-test-key-1234567890'],
        ], JSON_THROW_ON_ERROR)));
        $translator = new OpenAiTranslator('sk-test-key-1234567890', 'gpt-6-luna', $client);

        try {
            $translator->translate('en_US', 'English', 'ja', 'Japanese', new TranslationPayload('Hi', 'There', ''));
            $this->fail('Expected the API error to be raised.');
        } catch (TranslationException $exception) {
            $this->assertStringNotContainsString('sk-test-key-1234567890', $exception->getMessage());
            $this->assertStringContainsString('[redacted]', $exception->getMessage());
        }
    }

    public function test_oversized_posts_are_rejected_before_a_request(): void
    {
        $client = new FakeNeuronHttpClient(new HttpResponse(200, $this->envelope('{}')));
        $translator = new OpenAiTranslator('sk-test-key-1234567890', 'gpt-6-luna', $client);

        try {
            $translator->translate(
                'en_US',
                'English',
                'ja',
                'Japanese',
                new TranslationPayload('Hi', str_repeat('a', 80001), '')
            );
            $this->fail('Expected an oversized post to be rejected.');
        } catch (TranslationException $exception) {
            $this->assertSame([], $client->requests);
            $this->assertStringContainsString('too long', $exception->getMessage());
        }
    }

    public function test_invalid_model_json_is_a_failure(): void
    {
        $client = new FakeNeuronHttpClient(new HttpResponse(200, $this->envelope('{"title":')));
        $translator = new OpenAiTranslator('sk-test-key-1234567890', 'gpt-6-luna', $client);

        $this->expectException(TranslationException::class);
        $translator->translate('en_US', 'English', 'ja', 'Japanese', new TranslationPayload('Hi', 'There', ''));
    }

    private function translationJson(): string
    {
        return json_encode([
            'title' => 'Bonjour',
            'content' => '<p>Bonjour</p>',
            'excerpt' => 'Salut',
        ], JSON_THROW_ON_ERROR);
    }

    private function envelope(string $content): string
    {
        return json_encode([
            'status' => 'completed',
            'usage' => [
                'input_tokens' => 1,
                'output_tokens' => 1,
                'input_tokens_details' => ['cached_tokens' => 0],
                'output_tokens_details' => ['reasoning_tokens' => 0],
            ],
            'output' => [
                [
                    'type' => 'reasoning',
                    'content' => [],
                ],
                [
                    'type' => 'message',
                    'status' => 'completed',
                    'role' => 'assistant',
                    'content' => [
                        ['type' => 'output_text', 'text' => $content],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }
}
