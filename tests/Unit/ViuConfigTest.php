<?php

declare(strict_types=1);

namespace Viu\ViuLaravel\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Viu\ViuLaravel\ViuConfig;

final class ViuConfigTest extends TestCase
{
    public function test_from_array_full_config(): void
    {
        $config = ViuConfig::fromArray([
            'service_name'   => 'my-app',
            'environment'    => 'production',
            'transport_mode' => 'http',
            'http'           => [
                'api_url'     => 'https://api.viu.com',
                'api_key'     => 'viu_live_abc123',
                'timeout'     => 10,
                'max_retries' => 5,
            ],
            'batch_size'   => 50,
            'level'        => 'warning',
        ]);

        $this->assertSame('my-app', $config->serviceName);
        $this->assertSame('production', $config->environment);
        $this->assertSame('http', $config->transportMode);
        $this->assertSame('https://api.viu.com', $config->apiUrl);
        $this->assertSame('viu_live_abc123', $config->apiKey);
        $this->assertSame(10, $config->httpTimeout);
        $this->assertSame(5, $config->maxRetries);
        $this->assertSame(50, $config->batchSize);
        $this->assertSame('warning', $config->level);
    }

    public function test_from_array_defaults(): void
    {
        $config = ViuConfig::fromArray(['service_name' => 'test-svc']);

        $this->assertSame('production', $config->environment);
        $this->assertSame('http', $config->transportMode);
        $this->assertSame('', $config->apiUrl);
        $this->assertSame('', $config->apiKey);
        $this->assertSame(5, $config->httpTimeout);
        $this->assertSame(3, $config->maxRetries);
        $this->assertSame('localhost:9092', $config->kafkaBrokers);
        $this->assertSame('logs.app.raw', $config->kafkaTopic);
        $this->assertSame(100, $config->batchSize);
        $this->assertSame('debug', $config->level);
        $this->assertSame('X-Correlation-ID', $config->correlationIdHeader);
    }

    public function test_from_array_flat_http_config(): void
    {
        // Aceita estrutura plana (retrocompatibilidade)
        $config = ViuConfig::fromArray([
            'service_name' => 'test',
            'api_url'      => 'http://localhost:3000',
            'api_key'      => 'viu_live_flat',
        ]);

        $this->assertSame('http://localhost:3000', $config->apiUrl);
        $this->assertSame('viu_live_flat', $config->apiKey);
    }

    public function test_from_array_kafka_config(): void
    {
        $config = ViuConfig::fromArray([
            'service_name'   => 'test',
            'transport_mode' => 'kafka',
            'kafka'          => [
                'brokers'           => 'kafka.example.com:9092',
                'topic'             => 'logs.my-tenant',
                'username'          => 'user',
                'password'          => 'pass',
                'sasl_mechanism'    => 'SCRAM-SHA-512',
                'security_protocol' => 'SASL_PLAINTEXT',
            ],
        ]);

        $this->assertSame('kafka', $config->transportMode);
        $this->assertSame('kafka.example.com:9092', $config->kafkaBrokers);
        $this->assertSame('logs.my-tenant', $config->kafkaTopic);
        $this->assertSame('user', $config->kafkaUsername);
        $this->assertSame('pass', $config->kafkaPassword);
        $this->assertSame('SCRAM-SHA-512', $config->kafkaSaslMechanism);
        $this->assertSame('SASL_PLAINTEXT', $config->kafkaSecurityProtocol);
    }

    public function test_throws_on_invalid_transport_mode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/invalid transport mode/i');

        ViuConfig::fromArray([
            'service_name'   => 'test',
            'transport_mode' => 'websocket',
        ]);
    }

    public function test_from_env(): void
    {
        putenv('VIU_SERVICE_NAME=env-service');
        putenv('VIU_API_URL=http://localhost:3000');
        putenv('VIU_API_KEY=viu_live_env123');
        putenv('VIU_ENVIRONMENT=staging');
        putenv('VIU_BATCH_SIZE=25');

        try {
            $config = ViuConfig::fromEnv();

            $this->assertSame('env-service', $config->serviceName);
            $this->assertSame('http://localhost:3000', $config->apiUrl);
            $this->assertSame('viu_live_env123', $config->apiKey);
            $this->assertSame('staging', $config->environment);
            $this->assertSame(25, $config->batchSize);
        } finally {
            putenv('VIU_SERVICE_NAME');
            putenv('VIU_API_URL');
            putenv('VIU_API_KEY');
            putenv('VIU_ENVIRONMENT');
            putenv('VIU_BATCH_SIZE');
        }
    }

    public function test_is_immutable(): void
    {
        $config = ViuConfig::fromArray(['service_name' => 'immutable-test']);

        // Propriedades readonly não podem ser alteradas
        $this->assertSame('immutable-test', $config->serviceName);

        try {
            // @phpstan-ignore-next-line
            $config->serviceName = 'changed'; // @phpcs:ignore
            $this->fail('Expected error when modifying readonly property');
        } catch (\Error $e) {
            $this->assertStringContainsString('readonly', $e->getMessage());
        }
    }
}
