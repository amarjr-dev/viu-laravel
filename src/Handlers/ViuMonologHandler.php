<?php

declare(strict_types=1);

namespace Viu\ViuLaravel\Handlers;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Viu\ViuLaravel\Contracts\HttpClientInterface;
use Viu\ViuLaravel\Kafka\KafkaProducer;
use Viu\ViuLaravel\ViuConfig;

/**
 * Monolog Handler que encaminha logs ao backend Viu.
 *
 * Integra-se ao sistema de logging nativo do Laravel via driver 'viu'
 * (registrado pelo ViuServiceProvider em config/logging.php).
 *
 * Funcionalidades:
 *   - Smart batching: acumula logs e envia em lote (HTTP batch ou Kafka)
 *   - Flush automático ao atingir batchSize ou ao fim da requisição (shutdown function)
 *   - Propagação de Correlation ID / Trace ID / Span ID
 *   - Extração automática de dados de exceção do contexto
 *   - Mapeamento de todos os níveis Monolog → formato Viu
 */
final class ViuMonologHandler extends AbstractProcessingHandler
{
    /** @var array<int, array<string, mixed>> */
    private array $batch = [];

    private ?string $correlationId = null;
    private ?string $traceId = null;
    private ?string $spanId = null;

    public function __construct(
        private readonly ViuConfig $config,
        private readonly ?HttpClientInterface $httpClient = null,
        private readonly ?KafkaProducer $kafkaProducer = null,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);

        // Garante flush ao final da requisição (inclusive em contextos não-OOP como scripts CLI)
        register_shutdown_function([$this, 'flushBatch']);
    }

    // -------------------------------------------------------------------------
    // Correlation / Trace setters (chamados pelo ViuCorrelationMiddleware)
    // -------------------------------------------------------------------------

    public function setCorrelationId(?string $correlationId): void
    {
        $this->correlationId = $correlationId;
    }

    public function setTraceId(?string $traceId): void
    {
        $this->traceId = $traceId;
    }

    public function setSpanId(?string $spanId): void
    {
        $this->spanId = $spanId;
    }

    // -------------------------------------------------------------------------
    // Monolog AbstractProcessingHandler
    // -------------------------------------------------------------------------

    protected function write(LogRecord $record): void
    {
        $this->batch[] = $this->formatEntry($record);

        if (count($this->batch) >= $this->config->batchSize) {
            $this->flushBatch();
        }
    }

    // -------------------------------------------------------------------------
    // Batch management
    // -------------------------------------------------------------------------

    /**
     * Envia todos os logs acumulados no batch.
     * Chamado automaticamente em shutdown e ao atingir batchSize.
     * Pode ser chamado manualmente via Viu::flush() ou ViuLogger::flush().
     */
    public function flushBatch(): void
    {
        if (empty($this->batch)) {
            return;
        }

        $batch = $this->batch;
        $this->batch = [];

        try {
            if ($this->config->transportMode === 'kafka' && $this->kafkaProducer !== null) {
                foreach ($batch as $entry) {
                    $this->kafkaProducer->send((string) json_encode($entry));
                }
            } elseif ($this->httpClient !== null) {
                if (count($batch) === 1) {
                    $this->httpClient->send($batch[0]);
                } else {
                    $this->httpClient->sendBatch($batch);
                }
            }
        } catch (\Throwable) {
            // Logging must NEVER crash the application — falha silenciosa
        }
    }

    public function __destruct()
    {
        $this->flushBatch();
    }

    // -------------------------------------------------------------------------
    // Payload formatting
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function formatEntry(LogRecord $record): array
    {
        $correlationId = $this->correlationId ?? $this->generateUuid();
        $traceId       = $this->traceId ?? $correlationId;
        $spanId        = $this->spanId  ?? substr($this->generateUuid(), 0, 16);

        $context   = $record->context;
        $exception = null;

        // Extrai a exceção do contexto (Laravel coloca em 'exception')
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $e = $context['exception'];
            $exception = [
                'type'       => get_class($e),
                'message'    => $e->getMessage(),
                'code'       => $e->getCode(),
                'file'       => $e->getFile(),
                'line'       => $e->getLine(),
                'stacktrace' => $e->getTraceAsString(),
            ];
            unset($context['exception']);
        }

        $entry = [
            'timestamp'      => $record->datetime->format(\DateTimeInterface::RFC3339_EXTENDED),
            'level'          => $this->mapLevel($record->level),
            'message'        => $record->message,
            'service'        => $this->config->serviceName,
            'environment'    => $this->config->environment,
            'source'         => $record->channel,
            'correlation_id' => $correlationId,
            'trace_id'       => $traceId,
            'span_id'        => $spanId,
            'module'         => $record->channel,
            'file'           => (string) ($record->extra['file'] ?? ''),
            'line'           => (int)    ($record->extra['line'] ?? 0),
            'context'        => $context,
        ];

        if ($exception !== null) {
            $entry['context']['exception'] = $exception;
        }

        return $entry;
    }

    private function mapLevel(Level $level): string
    {
        return match ($level) {
            Level::Debug     => 'DEBUG',
            Level::Info      => 'INFO',
            Level::Notice    => 'INFO',
            Level::Warning   => 'WARNING',
            Level::Error     => 'ERROR',
            Level::Critical  => 'CRITICAL',
            Level::Alert     => 'CRITICAL',
            Level::Emergency => 'EMERGENCY',
        };
    }

    /**
     * Gera um UUID v4 sem dependências externas.
     */
    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        );
    }
}
