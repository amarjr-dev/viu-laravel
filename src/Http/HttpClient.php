<?php

declare(strict_types=1);

namespace Viu\ViuLaravel\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Viu\ViuLaravel\Contracts\HttpClientInterface;
use Viu\ViuLaravel\Support\CircuitBreaker;

/**
 * Cliente HTTP com retry automático e circuit breaker para envio de logs ao Viu.
 *
 * Retry em: 429, 500, 502, 503, 504 e falhas de rede (exceptions).
 * Backoff exponencial: 1s, 2s, 4s, ... até 10s.
 */
final class HttpClient implements HttpClientInterface
{
    private readonly Client $guzzle;
    private readonly CircuitBreaker $circuitBreaker;
    private readonly string $baseUrl;

    public function __construct(
        string $apiUrl,
        string $apiKey,
        int $timeout = 5,
        int $maxRetries = 3,
        ?CircuitBreaker $circuitBreaker = null,
        ?HandlerStack $handlerStack = null,
    ) {
        $this->baseUrl = rtrim($apiUrl, '/');
        $this->circuitBreaker = $circuitBreaker ?? new CircuitBreaker();

        if ($handlerStack === null) {
            $handlerStack = HandlerStack::create();
            $handlerStack->push(
                Middleware::retry(
                    decider: static function (
                        int $retries,
                        RequestInterface $request,
                        ?Response $response = null,
                        ?\Throwable $exception = null,
                    ) use ($maxRetries): bool {
                        if ($retries >= $maxRetries) {
                            return false;
                        }
                        if ($exception !== null) {
                            return true;
                        }
                        if ($response !== null
                            && in_array($response->getStatusCode(), [429, 500, 502, 503, 504], true)
                        ) {
                            return true;
                        }
                        return false;
                    },
                    delay: static fn (int $retries): int => (int) min(1000 * (2 ** ($retries - 1)), 10000),
                )
            );
        }

        $this->guzzle = new Client([
            'handler'         => $handlerStack,
            'timeout'         => $timeout,
            'connect_timeout' => 3,
            'headers'         => [
                'Content-Type'  => 'application/json',
                'Authorization' => "ApiKey {$apiKey}",
                'Accept'        => 'application/json',
            ],
        ]);
    }

    /**
     * Envia um único log entry ao backend.
     */
    public function send(array $logEntry): bool
    {
        if (empty($this->baseUrl)) {
            return false;
        }

        if (!$this->circuitBreaker->canAttempt()) {
            return false;
        }

        try {
            $response = $this->guzzle->post(
                $this->baseUrl . '/api/v1/logs',
                ['json' => $logEntry]
            );

            $success = $response->getStatusCode() < 400;
            $success
                ? $this->circuitBreaker->recordSuccess()
                : $this->circuitBreaker->recordFailure();

            return $success;
        } catch (GuzzleException) {
            $this->circuitBreaker->recordFailure();
            return false;
        }
    }

    /**
     * Envia múltiplos log entries em uma única requisição (batch).
     */
    public function sendBatch(array $logEntries): bool
    {
        if (empty($logEntries)) {
            return true;
        }

        if (empty($this->baseUrl)) {
            return false;
        }

        if (!$this->circuitBreaker->canAttempt()) {
            return false;
        }

        try {
            $response = $this->guzzle->post(
                $this->baseUrl . '/api/v1/logs',
                ['json' => $logEntries]
            );

            $success = $response->getStatusCode() < 400;
            $success
                ? $this->circuitBreaker->recordSuccess()
                : $this->circuitBreaker->recordFailure();

            return $success;
        } catch (GuzzleException) {
            $this->circuitBreaker->recordFailure();
            return false;
        }
    }

    public function getCircuitBreaker(): CircuitBreaker
    {
        return $this->circuitBreaker;
    }
}
