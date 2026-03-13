<?php

declare(strict_types=1);

namespace Viu\ViuLaravel;

use InvalidArgumentException;

/**
 * Value object imutável com todas as opções de configuração do SDK.
 *
 * Pode ser criado via:
 *   - ViuConfig::fromArray($config)  — a partir do array do config/viu.php
 *   - ViuConfig::fromEnv()           — a partir de variáveis de ambiente
 *   - new ViuConfig(...)             — diretamente (named arguments)
 */
final class ViuConfig
{
    public function __construct(
        public readonly string $serviceName,
        public readonly string $environment = 'production',
        public readonly string $transportMode = 'http',

        // HTTP
        public readonly string $apiUrl = '',
        public readonly string $apiKey = '',
        public readonly int    $httpTimeout = 5,
        public readonly int    $maxRetries = 3,

        // Kafka
        public readonly string $kafkaBrokers = 'localhost:9092',
        public readonly string $kafkaTopic = 'logs.app.raw',
        public readonly string $kafkaUsername = '',
        public readonly string $kafkaPassword = '',
        public readonly string $kafkaSaslMechanism = 'SCRAM-SHA-256',
        public readonly string $kafkaSecurityProtocol = 'SASL_SSL',

        // Common
        public readonly int    $batchSize = 100,
        public readonly string $level = 'debug',
        public readonly string $correlationIdHeader = 'X-Correlation-ID',
    ) {
        if (!in_array($transportMode, ['http', 'kafka'], true)) {
            throw new InvalidArgumentException(
                "Invalid transport mode: '{$transportMode}'. Allowed values: 'http', 'kafka'."
            );
        }
    }

    /**
     * Cria instância a partir do array retornado por config('viu').
     * Suporta tanto a estrutura aninhada (http.api_url) quanto plana (api_url).
     */
    public static function fromArray(array $config): self
    {
        return new self(
            serviceName:          (string) ($config['service_name'] ?? 'laravel-app'),
            environment:          (string) ($config['environment'] ?? 'production'),
            transportMode:        (string) ($config['transport_mode'] ?? 'http'),
            apiUrl:               (string) ($config['http']['api_url'] ?? $config['api_url'] ?? ''),
            apiKey:               (string) ($config['http']['api_key'] ?? $config['api_key'] ?? ''),
            httpTimeout:          (int)    ($config['http']['timeout'] ?? $config['http_timeout'] ?? 5),
            maxRetries:           (int)    ($config['http']['max_retries'] ?? $config['max_retries'] ?? 3),
            kafkaBrokers:         (string) ($config['kafka']['brokers'] ?? $config['kafka_brokers'] ?? 'localhost:9092'),
            kafkaTopic:           (string) ($config['kafka']['topic'] ?? $config['kafka_topic'] ?? 'logs.app.raw'),
            kafkaUsername:        (string) ($config['kafka']['username'] ?? $config['kafka_username'] ?? ''),
            kafkaPassword:        (string) ($config['kafka']['password'] ?? $config['kafka_password'] ?? ''),
            kafkaSaslMechanism:   (string) ($config['kafka']['sasl_mechanism'] ?? $config['kafka_sasl_mechanism'] ?? 'SCRAM-SHA-256'),
            kafkaSecurityProtocol:(string) ($config['kafka']['security_protocol'] ?? $config['kafka_security_protocol'] ?? 'SASL_SSL'),
            batchSize:            (int)    ($config['batch_size'] ?? 100),
            level:                (string) ($config['level'] ?? 'debug'),
            correlationIdHeader:  (string) ($config['correlation_id_header'] ?? 'X-Correlation-ID'),
        );
    }

    /**
     * Cria instância a partir de variáveis de ambiente.
     * Útil para uso standalone fora do Laravel.
     */
    public static function fromEnv(): self
    {
        return new self(
            serviceName:          (string) (getenv('VIU_SERVICE_NAME') ?: getenv('APP_NAME') ?: 'laravel-app'),
            environment:          (string) (getenv('VIU_ENVIRONMENT') ?: getenv('APP_ENV') ?: 'production'),
            transportMode:        (string) (getenv('VIU_TRANSPORT_MODE') ?: 'http'),
            apiUrl:               (string) (getenv('VIU_API_URL') ?: ''),
            apiKey:               (string) (getenv('VIU_API_KEY') ?: ''),
            httpTimeout:          (int)    (getenv('VIU_HTTP_TIMEOUT') ?: 5),
            maxRetries:           (int)    (getenv('VIU_HTTP_MAX_RETRIES') ?: 3),
            kafkaBrokers:         (string) (getenv('VIU_KAFKA_BROKERS') ?: 'localhost:9092'),
            kafkaTopic:           (string) (getenv('VIU_KAFKA_TOPIC') ?: 'logs.app.raw'),
            kafkaUsername:        (string) (getenv('VIU_KAFKA_USERNAME') ?: ''),
            kafkaPassword:        (string) (getenv('VIU_KAFKA_PASSWORD') ?: ''),
            kafkaSaslMechanism:   (string) (getenv('VIU_KAFKA_SASL_MECHANISM') ?: 'SCRAM-SHA-256'),
            kafkaSecurityProtocol:(string) (getenv('VIU_KAFKA_SECURITY_PROTOCOL') ?: 'SASL_SSL'),
            batchSize:            (int)    (getenv('VIU_BATCH_SIZE') ?: 100),
            level:                (string) (getenv('VIU_LOG_LEVEL') ?: 'debug'),
            correlationIdHeader:  (string) (getenv('VIU_CORRELATION_ID_HEADER') ?: 'X-Correlation-ID'),
        );
    }
}
