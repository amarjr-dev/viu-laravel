<?php

declare(strict_types=1);

namespace Viu\ViuLaravel;

use Monolog\Level;
use Monolog\Logger;
use Viu\ViuLaravel\Handlers\ViuMonologHandler;
use Viu\ViuLaravel\Http\HttpClient;
use Viu\ViuLaravel\Kafka\KafkaProducer;

/**
 * Logger principal do SDK viu-laravel.
 *
 * Pode ser usado standalone ou via Facade:
 *
 *   // Acesso direto via DI / contêiner
 *   app(ViuLogger::class)->info('User logged in', ['user_id' => 123]);
 *
 *   // Via Facade
 *   Viu::error('Payment failed', ['amount' => 99.90]);
 *
 *   // Via canal Monolog (integração com Log::channel())
 *   Log::channel('viu')->info('User logged in');
 */
class ViuLogger
{
    private readonly Logger $monolog;
    private readonly ViuMonologHandler $handler;

    public function __construct(
        private readonly ViuConfig $config,
    ) {
        $httpClient    = null;
        $kafkaProducer = null;

        if ($config->transportMode === 'http') {
            $httpClient = new HttpClient(
                apiUrl:     $config->apiUrl,
                apiKey:     $config->apiKey,
                timeout:    $config->httpTimeout,
                maxRetries: $config->maxRetries,
            );
        } else {
            $kafkaProducer = new KafkaProducer(
                brokers:          $config->kafkaBrokers,
                topic:            $config->kafkaTopic,
                username:         $config->kafkaUsername,
                password:         $config->kafkaPassword,
                saslMechanism:    $config->kafkaSaslMechanism,
                securityProtocol: $config->kafkaSecurityProtocol,
            );
        }

        $this->handler = new ViuMonologHandler(
            config:        $config,
            httpClient:    $httpClient,
            kafkaProducer: $kafkaProducer,
            level:         self::parseLevel($config->level),
        );

        $this->monolog = new Logger($config->serviceName, [$this->handler]);
    }

    // -------------------------------------------------------------------------
    // Correlation / Trace context
    // -------------------------------------------------------------------------

    public function setCorrelationId(?string $correlationId): void
    {
        $this->handler->setCorrelationId($correlationId);
    }

    public function setTraceId(?string $traceId): void
    {
        $this->handler->setTraceId($traceId);
    }

    public function setSpanId(?string $spanId): void
    {
        $this->handler->setSpanId($spanId);
    }

    // -------------------------------------------------------------------------
    // PSR-3 logging methods
    // -------------------------------------------------------------------------

    public function debug(string $message, array $context = []): void
    {
        $this->monolog->debug($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->monolog->info($message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->monolog->notice($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->monolog->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->monolog->error($message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->monolog->critical($message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->monolog->alert($message, $context);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->monolog->emergency($message, $context);
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    /**
     * Força o envio imediato de todos os logs pendentes no batch.
     * Útil em queue workers, jobs longos ou testes.
     */
    public function flush(): void
    {
        $this->handler->flushBatch();
    }

    public function getConfig(): ViuConfig
    {
        return $this->config;
    }

    public function getHandler(): ViuMonologHandler
    {
        return $this->handler;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    public static function parseLevel(string $level): Level
    {
        return match (strtolower($level)) {
            'debug'                => Level::Debug,
            'info'                 => Level::Info,
            'notice'               => Level::Notice,
            'warning', 'warn'      => Level::Warning,
            'error'                => Level::Error,
            'critical'             => Level::Critical,
            'alert'                => Level::Alert,
            'emergency'            => Level::Emergency,
            default                => Level::Debug,
        };
    }
}
