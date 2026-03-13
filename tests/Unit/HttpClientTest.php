<?php

declare(strict_types=1);

namespace Viu\ViuLaravel\Tests\Unit;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Viu\ViuLaravel\Http\HttpClient;
use Viu\ViuLaravel\Support\CircuitBreaker;

final class HttpClientTest extends TestCase
{
    private function makeClient(
        array $responses,
        ?CircuitBreaker $cb = null,
        int $maxRetries = 0,
    ): HttpClient {
        $mock  = new MockHandler($responses);
        $stack = HandlerStack::create($mock);

        return new HttpClient(
            apiUrl:       'http://localhost:3000',
            apiKey:       'viu_live_test',
            timeout:      5,
            maxRetries:   $maxRetries,
            circuitBreaker: $cb ?? new CircuitBreaker(),
            handlerStack: $stack,
        );
    }

    public function test_send_returns_true_on_202(): void
    {
        $client = $this->makeClient([new Response(202)]);
        $this->assertTrue($client->send(['level' => 'INFO', 'message' => 'test']));
    }

    public function test_send_returns_true_on_200(): void
    {
        $client = $this->makeClient([new Response(200)]);
        $this->assertTrue($client->send(['level' => 'DEBUG', 'message' => 'ok']));
    }

    public function test_send_returns_false_on_server_error(): void
    {
        $client = $this->makeClient([new Response(500)]);
        $this->assertFalse($client->send(['level' => 'ERROR', 'message' => 'fail']));
    }

    public function test_send_returns_false_on_unauthorized(): void
    {
        $client = $this->makeClient([new Response(401)]);
        $this->assertFalse($client->send(['level' => 'INFO', 'message' => 'test']));
    }

    public function test_send_returns_false_on_connection_error(): void
    {
        $mock  = new MockHandler([
            new ConnectException('Connection refused', new Request('POST', '/api/v1/logs')),
        ]);
        $stack = HandlerStack::create($mock);
        $client = new HttpClient('http://localhost:3000', 'viu_live_test', 5, 0, new CircuitBreaker(), $stack);

        $this->assertFalse($client->send(['level' => 'INFO', 'message' => 'test']));
    }

    public function test_send_returns_false_when_url_is_empty(): void
    {
        $mock  = new MockHandler([]);
        $stack = HandlerStack::create($mock);
        $client = new HttpClient('', 'viu_live_test', 5, 0, new CircuitBreaker(), $stack);

        $this->assertFalse($client->send(['level' => 'INFO', 'message' => 'test']));
    }

    public function test_circuit_breaker_blocks_when_open(): void
    {
        $cb = new CircuitBreaker(failureThreshold: 1, timeout: 60);
        $cb->recordFailure(); // força abertura do circuito

        // MockHandler vazio — nenhuma requisição deve ser feita
        $mock  = new MockHandler([]);
        $stack = HandlerStack::create($mock);
        $client = new HttpClient('http://localhost:3000', 'viu_live_test', 5, 0, $cb, $stack);

        $this->assertFalse($client->send(['level' => 'INFO', 'message' => 'test']));
    }

    public function test_send_updates_circuit_breaker_on_success(): void
    {
        $cb     = new CircuitBreaker(failureThreshold: 5);
        $client = $this->makeClient([new Response(202)], $cb);

        $client->send(['level' => 'INFO', 'message' => 'test']);

        $this->assertSame('closed', $cb->getState());
    }

    public function test_send_updates_circuit_breaker_on_failure(): void
    {
        $cb     = new CircuitBreaker(failureThreshold: 1);
        $client = $this->makeClient([new Response(500)], $cb);

        $client->send(['level' => 'ERROR', 'message' => 'fail']);

        $this->assertSame('open', $cb->getState());
    }

    public function test_send_batch_returns_true_on_success(): void
    {
        $client = $this->makeClient([new Response(202)]);

        $result = $client->sendBatch([
            ['level' => 'INFO',  'message' => 'log 1'],
            ['level' => 'ERROR', 'message' => 'log 2'],
        ]);

        $this->assertTrue($result);
    }

    public function test_send_batch_returns_true_for_empty_array(): void
    {
        // Nenhuma requisição deve ser feita para batch vazio
        $client = $this->makeClient([]);
        $this->assertTrue($client->sendBatch([]));
    }

    public function test_send_batch_returns_false_on_server_error(): void
    {
        $client = $this->makeClient([new Response(503)]);
        $this->assertFalse($client->sendBatch([['level' => 'INFO', 'message' => 'test']]));
    }

    public function test_get_circuit_breaker_returns_instance(): void
    {
        $cb     = new CircuitBreaker();
        $client = $this->makeClient([new Response(200)], $cb);

        $this->assertSame($cb, $client->getCircuitBreaker());
    }
}
