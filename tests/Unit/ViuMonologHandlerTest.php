<?php

declare(strict_types=1);

namespace Viu\ViuLaravel\Tests\Unit;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Viu\ViuLaravel\Contracts\HttpClientInterface;
use Viu\ViuLaravel\Handlers\ViuMonologHandler;
use Viu\ViuLaravel\ViuConfig;

final class ViuMonologHandlerTest extends TestCase
{
    private function makeConfig(array $overrides = []): ViuConfig
    {
        return ViuConfig::fromArray(array_merge([
            'service_name'   => 'test-service',
            'environment'    => 'testing',
            'transport_mode' => 'http',
            'http'           => ['api_url' => 'http://localhost:3000', 'api_key' => 'viu_live_test'],
            'batch_size'     => 1, // flush imediato nos testes
        ], $overrides));
    }

    private function makeRecord(
        string $message = 'Test message',
        Level $level = Level::Info,
        array $context = [],
        string $channel = 'test',
    ): LogRecord {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel:  $channel,
            level:    $level,
            message:  $message,
            context:  $context,
            extra:    [],
        );
    }

    public function test_payload_contains_all_required_fields(): void
    {
        $httpClient  = $this->createMock(HttpClientInterface::class);
        $sentPayload = null;
        $httpClient->method('send')->willReturnCallback(function (array $entry) use (&$sentPayload) {
            $sentPayload = $entry;
            return true;
        });

        $handler = new ViuMonologHandler($this->makeConfig(), $httpClient, null, Level::Debug);
        $handler->handle($this->makeRecord('Hello Viu', Level::Info, ['user_id' => '123']));
        $handler->flushBatch();

        $this->assertNotNull($sentPayload);
        $this->assertArrayHasKey('timestamp', $sentPayload);
        $this->assertArrayHasKey('level', $sentPayload);
        $this->assertArrayHasKey('message', $sentPayload);
        $this->assertArrayHasKey('service', $sentPayload);
        $this->assertArrayHasKey('environment', $sentPayload);
        $this->assertArrayHasKey('source', $sentPayload);
        $this->assertArrayHasKey('correlation_id', $sentPayload);
        $this->assertArrayHasKey('trace_id', $sentPayload);
        $this->assertArrayHasKey('span_id', $sentPayload);
        $this->assertArrayHasKey('module', $sentPayload);
        $this->assertArrayHasKey('file', $sentPayload);
        $this->assertArrayHasKey('line', $sentPayload);
        $this->assertArrayHasKey('context', $sentPayload);
    }

    public function test_payload_values_are_correct(): void
    {
        $httpClient  = $this->createMock(HttpClientInterface::class);
        $sentPayload = null;
        $httpClient->method('send')->willReturnCallback(function (array $entry) use (&$sentPayload) {
            $sentPayload = $entry;
            return true;
        });

        $handler = new ViuMonologHandler($this->makeConfig(), $httpClient, null, Level::Debug);
        $handler->handle($this->makeRecord('Payment failed', Level::Error, ['amount' => 99.90]));
        $handler->flushBatch();

        $this->assertSame('test-service', $sentPayload['service']);
        $this->assertSame('testing', $sentPayload['environment']);
        $this->assertSame('Payment failed', $sentPayload['message']);
        $this->assertSame('ERROR', $sentPayload['level']);
        $this->assertSame(99.90, $sentPayload['context']['amount']);
    }

    public function test_level_mapping_debug(): void
    {
        $this->assertLevelMappedTo(Level::Debug, 'DEBUG');
    }

    public function test_level_mapping_info(): void
    {
        $this->assertLevelMappedTo(Level::Info, 'INFO');
    }

    public function test_level_mapping_notice_maps_to_info(): void
    {
        $this->assertLevelMappedTo(Level::Notice, 'INFO');
    }

    public function test_level_mapping_warning(): void
    {
        $this->assertLevelMappedTo(Level::Warning, 'WARNING');
    }

    public function test_level_mapping_error(): void
    {
        $this->assertLevelMappedTo(Level::Error, 'ERROR');
    }

    public function test_level_mapping_critical(): void
    {
        $this->assertLevelMappedTo(Level::Critical, 'CRITICAL');
    }

    public function test_level_mapping_emergency(): void
    {
        $this->assertLevelMappedTo(Level::Emergency, 'EMERGENCY');
    }

    public function test_correlation_id_is_propagated(): void
    {
        $httpClient  = $this->createMock(HttpClientInterface::class);
        $sentPayload = null;
        $httpClient->method('send')->willReturnCallback(function (array $entry) use (&$sentPayload) {
            $sentPayload = $entry;
            return true;
        });

        $handler = new ViuMonologHandler($this->makeConfig(), $httpClient, null, Level::Debug);
        $handler->setCorrelationId('my-correlation-id-123');
        $handler->handle($this->makeRecord());
        $handler->flushBatch();

        $this->assertSame('my-correlation-id-123', $sentPayload['correlation_id']);
    }

    public function test_trace_id_defaults_to_correlation_id(): void
    {
        $httpClient  = $this->createMock(HttpClientInterface::class);
        $sentPayload = null;
        $httpClient->method('send')->willReturnCallback(function (array $entry) use (&$sentPayload) {
            $sentPayload = $entry;
            return true;
        });

        $handler = new ViuMonologHandler($this->makeConfig(), $httpClient, null, Level::Debug);
        $handler->setCorrelationId('corr-abc');
        // Não define traceId — deve herdar o correlationId
        $handler->handle($this->makeRecord());
        $handler->flushBatch();

        $this->assertSame('corr-abc', $sentPayload['correlation_id']);
        $this->assertSame('corr-abc', $sentPayload['trace_id']);
    }

    public function test_custom_trace_id_is_used(): void
    {
        $httpClient  = $this->createMock(HttpClientInterface::class);
        $sentPayload = null;
        $httpClient->method('send')->willReturnCallback(function (array $entry) use (&$sentPayload) {
            $sentPayload = $entry;
            return true;
        });

        $handler = new ViuMonologHandler($this->makeConfig(), $httpClient, null, Level::Debug);
        $handler->setCorrelationId('corr-xyz');
        $handler->setTraceId('trace-custom-789');
        $handler->handle($this->makeRecord());
        $handler->flushBatch();

        $this->assertSame('corr-xyz', $sentPayload['correlation_id']);
        $this->assertSame('trace-custom-789', $sentPayload['trace_id']);
    }

    public function test_exception_in_context_is_formatted(): void
    {
        $httpClient  = $this->createMock(HttpClientInterface::class);
        $sentPayload = null;
        $httpClient->method('send')->willReturnCallback(function (array $entry) use (&$sentPayload) {
            $sentPayload = $entry;
            return true;
        });

        $handler   = new ViuMonologHandler($this->makeConfig(), $httpClient, null, Level::Debug);
        $exception = new \RuntimeException('Payment failed', 42);

        $handler->handle($this->makeRecord(
            'Error occurred',
            Level::Error,
            ['exception' => $exception, 'order_id' => 'ord-123'],
        ));
        $handler->flushBatch();

        $this->assertArrayHasKey('exception', $sentPayload['context']);
        $exc = $sentPayload['context']['exception'];
        $this->assertSame(\RuntimeException::class, $exc['type']);
        $this->assertSame('Payment failed', $exc['message']);
        $this->assertSame(42, $exc['code']);
        $this->assertArrayHasKey('stacktrace', $exc);

        // Outros campos do contexto devem permanecer
        $this->assertSame('ord-123', $sentPayload['context']['order_id']);
    }

    public function test_batch_sends_single_via_send(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())->method('send')->willReturn(true);
        $httpClient->expects($this->never())->method('sendBatch');

        $config  = $this->makeConfig(['batch_size' => 1]);
        $handler = new ViuMonologHandler($config, $httpClient, null, Level::Debug);
        $handler->handle($this->makeRecord());
        $handler->flushBatch();
    }

    public function test_batch_sends_multiple_via_send_batch(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('send');
        $httpClient->expects($this->once())->method('sendBatch')->willReturn(true);

        $config  = $this->makeConfig(['batch_size' => 10]);
        $handler = new ViuMonologHandler($config, $httpClient, null, Level::Debug);

        $handler->handle($this->makeRecord('msg 1'));
        $handler->handle($this->makeRecord('msg 2'));
        $handler->handle($this->makeRecord('msg 3'));
        $handler->flushBatch();
    }

    public function test_flush_batch_is_idempotent(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())->method('send')->willReturn(true);

        $config  = $this->makeConfig(['batch_size' => 1]);
        $handler = new ViuMonologHandler($config, $httpClient, null, Level::Debug);

        $handler->handle($this->makeRecord());
        $handler->flushBatch(); // já foi flushed no handle (batch_size=1)
        $handler->flushBatch(); // chamada extra não deve enviar novamente
    }

    public function test_handler_level_filters_records(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('send');
        $httpClient->expects($this->never())->method('sendBatch');

        // Handler configurado para WARNING, mas enviamos DEBUG
        $handler = new ViuMonologHandler($this->makeConfig(), $httpClient, null, Level::Warning);
        $handler->handle($this->makeRecord('debug msg', Level::Debug));
        $handler->flushBatch();
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function assertLevelMappedTo(Level $monologLevel, string $expectedViuLevel): void
    {
        $httpClient  = $this->createMock(HttpClientInterface::class);
        $sentPayload = null;
        $httpClient->method('send')->willReturnCallback(function (array $entry) use (&$sentPayload) {
            $sentPayload = $entry;
            return true;
        });

        $handler = new ViuMonologHandler($this->makeConfig(), $httpClient, null, Level::Debug);
        $handler->handle($this->makeRecord('test', $monologLevel));
        $handler->flushBatch();

        $this->assertSame($expectedViuLevel, $sentPayload['level'] ?? null);
    }
}
