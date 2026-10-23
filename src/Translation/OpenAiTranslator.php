<?php

declare(strict_types=1);

namespace WpAutoTranslator\Translation;

use NeuronAI\Agent\Agent;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\NeuronException;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\Providers\OpenAI\Responses\OpenAIResponses;

if (! defined('ABSPATH') && ! defined('WAT_TESTING')) {
    exit;
}

/**
 * One structured Responses API call through Neuron.
 *
 * Neuron's default message store is in memory, so this does not create chat tables.
 * Temperature is left unset: several current models reject it on the Responses API.
 */
final class OpenAiTranslator implements Translator
{
    private const MAX_INPUT_CHARS = 80000;

    public function __construct(
        private string $apiKey,
        private string $model,
        private ?HttpClientInterface $httpClient = null,
        private PromptBuilder $prompts = new PromptBuilder(),
    ) {
    }

    public function translate(
        string $sourceLocale,
        string $sourceLanguage,
        string $targetLocale,
        string $targetLanguage,
        TranslationPayload $payload
    ): TranslationPayload {
        $size = strlen($payload->title) + strlen($payload->content) + strlen($payload->excerpt);
        if ($size > self::MAX_INPUT_CHARS) {
            throw new TranslationException('This post is too long to translate in one request.');
        }

        $result = $this->ask(
            $this->prompts->system($sourceLocale, $sourceLanguage, $targetLocale, $targetLanguage),
            $this->prompts->user($payload),
            TranslatedPost::class,
        );

        if (! $result instanceof TranslatedPost) {
            throw new TranslationException('OpenAI returned no translation.');
        }

        $title = trim($result->title);
        if ($title === '') {
            throw new TranslationException('The model returned an empty title.');
        }
        if (mb_strlen($title) > 400) {
            throw new TranslationException('The translated title is too long.');
        }

        return new TranslationPayload($title, $result->content, trim($result->excerpt));
    }

    public function ping(): void
    {
        $result = $this->ask(
            'Set ok to true in the json response and do nothing else.',
            'Confirm the connection with json.',
            ConnectionCheck::class,
        );

        if (! $result instanceof ConnectionCheck || $result->ok !== true) {
            throw new TranslationException('OpenAI responded, but the connection check failed.');
        }
    }

    /**
     * @param class-string $class
     */
    private function ask(string $instructions, string $input, string $class): object
    {
        if (! class_exists(Agent::class)) {
            throw new TranslationException('Neuron is not installed. Run composer install in the plugin directory.');
        }

        try {
            $provider = new OpenAIResponses(
                key: $this->apiKey,
                model: $this->model,
                parameters: $this->parameters(),
                strict_response: true,
                httpClient: $this->httpClient,
            );

            $result = Agent::make()
                ->setAiProvider($provider)
                ->setThreadId(uniqid('wat-', true))
                ->setInstructions($instructions)
                ->structured(new UserMessage($input), $class, 0);
        } catch (TranslationException $exception) {
            throw $exception;
        } catch (NeuronException $exception) {
            throw new TranslationException($this->withoutKey($exception->getMessage()), 0, $exception);
        }

        if (! is_object($result)) {
            throw new TranslationException('OpenAI returned no translation.');
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function parameters(): array
    {
        $parameters = [
            'max_output_tokens' => 16384,
            'store' => false,
        ];

        if (function_exists('apply_filters')) {
            $filtered = apply_filters('wat_openai_request_parameters', $parameters, $this->model);
            if (is_array($filtered)) {
                $parameters = $filtered;
            }
        }

        return $parameters;
    }

    private function withoutKey(string $message): string
    {
        if ($this->apiKey === '') {
            return $message;
        }

        return str_replace($this->apiKey, '[redacted]', $message);
    }
}
