<?php

declare(strict_types=1);

namespace Viu\ViuLaravel\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;
use Viu\ViuLaravel\Middleware\ViuCorrelationMiddleware;
use Viu\ViuLaravel\ViuConfig;
use Viu\ViuLaravel\ViuLogger;

final class MiddlewareTest extends TestCase
{
    private function makeLogger(?ViuConfig $config = null): ViuLogger
    {
        // Usa mock para não precisar de backend disponível
        return $this->getMockBuilder(ViuLogger::class)
            ->setConstructorArgs([
                $config ?? ViuConfig::fromArray([
                    'service_name' => 'test',
                    'http'         => ['api_url' => '', 'api_key' => ''],
                ]),
            ])
            ->onlyMethods([])
            ->getMock();
    }

    private function nextHandler(): \Closure
    {
        return static fn (Request $req): Response => new Response('OK');
    }

    public function test_sets_correlation_id_from_request_header(): void
    {
        $logger = $this->createMock(ViuLogger::class);
        $logger->expects($this->once())
               ->method('setCorrelationId')
               ->with('req-correlation-abc');

        $logger->method('getConfig')->willReturn(
            ViuConfig::fromArray(['service_name' => 'test'])
        );

        $middleware = new ViuCorrelationMiddleware($logger);
        $request    = Request::create('/test', 'GET', [], [], [], [
            'HTTP_X-Correlation-ID' => 'req-correlation-abc',
        ]);

        $middleware->handle($request, $this->nextHandler());
    }

    public function test_generates_uuid_when_header_is_absent(): void
    {
        $receivedId = null;
        $logger     = $this->createMock(ViuLogger::class);
        $logger->method('setCorrelationId')->willReturnCallback(
            function (?string $id) use (&$receivedId): void {
                $receivedId = $id;
            }
        );
        $logger->method('getConfig')->willReturn(
            ViuConfig::fromArray(['service_name' => 'test'])
        );

        $middleware = new ViuCorrelationMiddleware($logger);
        $middleware->handle(Request::create('/test', 'GET'), $this->nextHandler());

        $this->assertNotNull($receivedId);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $receivedId,
        );
    }

    public function test_adds_correlation_id_header_to_response(): void
    {
        $logger = $this->createMock(ViuLogger::class);
        $logger->method('setCorrelationId');
        $logger->method('setTraceId');
        $logger->method('setSpanId');
        $logger->method('getConfig')->willReturn(
            ViuConfig::fromArray(['service_name' => 'test'])
        );

        $middleware = new ViuCorrelationMiddleware($logger);
        $request    = Request::create('/test', 'GET', [], [], [], [
            'HTTP_X-Correlation-ID' => 'test-id-123',
        ]);

        $response = $middleware->handle($request, $this->nextHandler());

        $this->assertSame('test-id-123', $response->headers->get('X-Correlation-ID'));
    }

    public function test_adds_trace_id_header_to_response(): void
    {
        $logger = $this->createMock(ViuLogger::class);
        $logger->method('setCorrelationId');
        $logger->method('setTraceId');
        $logger->method('setSpanId');
        $logger->method('getConfig')->willReturn(
            ViuConfig::fromArray(['service_name' => 'test'])
        );

        $middleware = new ViuCorrelationMiddleware($logger);
        $request    = Request::create('/test', 'GET', [], [], [], [
            'HTTP_X-Correlation-ID' => 'corr-xyz',
            'HTTP_X-Trace-ID'       => 'trace-custom',
        ]);

        $response = $middleware->handle($request, $this->nextHandler());

        $this->assertSame('trace-custom', $response->headers->get('X-Trace-ID'));
    }

    public function test_trace_id_defaults_to_correlation_id(): void
    {
        $receivedTraceId = null;
        $logger          = $this->createMock(ViuLogger::class);
        $logger->method('setCorrelationId');
        $logger->method('setTraceId')->willReturnCallback(
            function (?string $id) use (&$receivedTraceId): void {
                $receivedTraceId = $id;
            }
        );
        $logger->method('setSpanId');
        $logger->method('getConfig')->willReturn(
            ViuConfig::fromArray(['service_name' => 'test'])
        );

        $middleware = new ViuCorrelationMiddleware($logger);
        $request    = Request::create('/test', 'GET', [], [], [], [
            'HTTP_X-Correlation-ID' => 'my-corr-id',
            // Sem X-Trace-ID — deve usar o correlation ID
        ]);

        $middleware->handle($request, $this->nextHandler());

        $this->assertSame('my-corr-id', $receivedTraceId);
    }

    public function test_respects_custom_correlation_id_header_from_config(): void
    {
        $receivedId = null;
        $logger     = $this->createMock(ViuLogger::class);
        $logger->method('setCorrelationId')->willReturnCallback(
            function (?string $id) use (&$receivedId): void {
                $receivedId = $id;
            }
        );
        $logger->method('setTraceId');
        $logger->method('setSpanId');
        $logger->method('getConfig')->willReturn(
            ViuConfig::fromArray([
                'service_name'         => 'test',
                'correlation_id_header' => 'X-Request-ID',
            ])
        );

        $middleware = new ViuCorrelationMiddleware($logger);
        $request    = Request::create('/test', 'GET', [], [], [], [
            'HTTP_X-Request-ID' => 'custom-header-value',
        ]);

        $middleware->handle($request, $this->nextHandler());

        $this->assertSame('custom-header-value', $receivedId);
    }
}
