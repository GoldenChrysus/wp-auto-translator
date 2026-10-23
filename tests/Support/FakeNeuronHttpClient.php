<?php

declare(strict_types=1);

namespace WpAutoTranslator\Tests\Support;

use NeuronAI\Exceptions\HttpException;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\HttpClient\HttpRequest;
use NeuronAI\HttpClient\HttpResponse;
use NeuronAI\HttpClient\StreamInterface;

final class FakeNeuronHttpClient implements HttpClientInterface
{
    /**
     * @var list<HttpRequest>
     */
    public array $requests = [];

    public function __construct(private HttpResponse $response)
    {
    }

    public function request(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        if ($this->response->statusCode >= 400) {
            throw HttpException::statusError($request, $this->response);
        }

        return $this->response;
    }

    public function stream(HttpRequest $request): StreamInterface
    {
        unset($request);
        throw new \RuntimeException('This test client does not stream.');
    }
}
