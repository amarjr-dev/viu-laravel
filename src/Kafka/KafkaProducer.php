<?php

declare(strict_types=1);

namespace Viu\ViuLaravel\Kafka;

use RuntimeException;

/**
 * Kafka Producer para envio de logs diretamente ao tópico Kafka.
 *
 * Requer: composer require longlang/phpkafka
 *
 * Este é o modo alternativo (legacy). O modo HTTP é recomendado pois não expõe
 * o Kafka diretamente e simplifica a configuração.
 */
final class KafkaProducer
{
    /** @var \longlang\phpkafka\Producer\KafkaProducer|null */
    private mixed $producer = null;
    private bool $connected = false;

    public function __construct(
        private readonly string $brokers,
        private readonly string $topic,
        private readonly string $username = '',
        private readonly string $password = '',
        private readonly string $saslMechanism = 'SCRAM-SHA-256',
        private readonly string $securityProtocol = 'SASL_SSL',
    ) {
        if (!class_exists(\longlang\phpkafka\Producer\KafkaProducer::class)) {
            throw new RuntimeException(
                'longlang/phpkafka is required for Kafka transport mode. '
                . 'Install it with: composer require longlang/phpkafka'
            );
        }
    }

    private function connect(): void
    {
        if ($this->connected) {
            return;
        }

        /** @var \longlang\phpkafka\Producer\ProducerConfig $config */
        $config = new \longlang\phpkafka\Producer\ProducerConfig();
        $config->setBootstrapServer($this->brokers);
        $config->setUpdateBrokers(true);
        $config->setAcks(-1);

        if ($this->username !== '' && $this->password !== '') {
            /** @var \longlang\phpkafka\Sasl\SaslConfig $sasl */
            $sasl = new \longlang\phpkafka\Sasl\SaslConfig();
            $sasl->setType(\longlang\phpkafka\Sasl\SaslConfig::PLAIN);
            $sasl->setUsername($this->username);
            $sasl->setPassword($this->password);
            $config->setSasl($sasl);
        }

        $this->producer = new \longlang\phpkafka\Producer\KafkaProducer($config);
        $this->connected = true;
    }

    /**
     * Publica um log serializado como JSON no tópico Kafka.
     */
    public function send(string $json): bool
    {
        try {
            $this->connect();
            $this->producer->send($this->topic, null, $json);
            return true;
        } catch (\Throwable $e) {
            error_log('[viu-laravel] Kafka send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fecha a conexão com o Kafka.
     */
    public function close(): void
    {
        if ($this->connected && $this->producer !== null) {
            try {
                $this->producer->close();
            } catch (\Throwable) {
                // silent
            }
            $this->connected = false;
            $this->producer = null;
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}
