<?php

declare(strict_types=1);

namespace Viu\ViuLaravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Monolog\Logger;
use Viu\ViuLaravel\Handlers\ViuMonologHandler;
use Viu\ViuLaravel\Http\HttpClient;
use Viu\ViuLaravel\Kafka\KafkaProducer;

/**
 * Service Provider do SDK viu-laravel.
 *
 * Registra:
 *   - 'viu'         → singleton ViuLogger (usado pela Facade Viu::)
 *   - driver 'viu'  → canal Monolog customizado (Log::channel('viu'))
 *
 * Auto-discovery via composer.json (extra.laravel.providers).
 */
class ViuServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/viu.php', 'viu');

        $this->app->singleton('viu', function (Application $app) {
            $config = ViuConfig::fromArray($app['config']->get('viu', []));
            return new ViuLogger($config);
        });

        $this->app->alias('viu', ViuLogger::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/viu.php' => config_path('viu.php'),
            ], 'viu-config');
        }

        // Registra o driver 'viu' para config/logging.php
        // Permite: Log::channel('viu')->info('...')
        // Ou stack: ['driver' => 'stack', 'channels' => ['daily', 'viu']]
        $this->callAfterResolving('log', function (\Illuminate\Log\LogManager $log) {
            $log->extend('viu', function (Application $app, array $channelConfig) {
                // Mescla config global do viu.php com overrides do canal em logging.php
                $viuConfig = ViuConfig::fromArray(array_merge(
                    $app['config']->get('viu', []),
                    $channelConfig,
                ));

                $httpClient    = null;
                $kafkaProducer = null;

                if ($viuConfig->transportMode === 'http') {
                    $httpClient = new HttpClient(
                        apiUrl:     $viuConfig->apiUrl,
                        apiKey:     $viuConfig->apiKey,
                        timeout:    $viuConfig->httpTimeout,
                        maxRetries: $viuConfig->maxRetries,
                    );
                } else {
                    $kafkaProducer = new KafkaProducer(
                        brokers:          $viuConfig->kafkaBrokers,
                        topic:            $viuConfig->kafkaTopic,
                        username:         $viuConfig->kafkaUsername,
                        password:         $viuConfig->kafkaPassword,
                        saslMechanism:    $viuConfig->kafkaSaslMechanism,
                        securityProtocol: $viuConfig->kafkaSecurityProtocol,
                    );
                }

                // Nível do canal pode ser sobrescrito em config/logging.php
                $level = ViuLogger::parseLevel(
                    $channelConfig['level'] ?? $viuConfig->level
                );

                $handler = new ViuMonologHandler(
                    config:        $viuConfig,
                    httpClient:    $httpClient,
                    kafkaProducer: $kafkaProducer,
                    level:         $level,
                );

                return new Logger('viu', [$handler]);
            });
        });
    }
}
