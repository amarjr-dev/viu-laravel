<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nome do Serviço
    |--------------------------------------------------------------------------
    | Identificador deste serviço/aplicação nos logs enviados ao Viu.
    | Valor padrão: APP_NAME do .env
    */
    'service_name' => env('VIU_SERVICE_NAME', env('APP_NAME', 'laravel-app')),

    /*
    |--------------------------------------------------------------------------
    | Ambiente
    |--------------------------------------------------------------------------
    | Ambiente de execução (ex: development, staging, production).
    | Valor padrão: APP_ENV do .env
    */
    'environment' => env('VIU_ENVIRONMENT', env('APP_ENV', 'production')),

    /*
    |--------------------------------------------------------------------------
    | Modo de Transporte
    |--------------------------------------------------------------------------
    | Define como os logs são enviados ao Viu.
    |
    | Opções:
    |   'http'  — (recomendado) via API REST com autenticação por API Key
    |   'kafka' — diretamente ao Kafka (requer longlang/phpkafka)
    */
    'transport_mode' => env('VIU_TRANSPORT_MODE', 'http'),

    /*
    |--------------------------------------------------------------------------
    | Configuração HTTP
    |--------------------------------------------------------------------------
    | Usada quando transport_mode = 'http'.
    */
    'http' => [
        'api_url'     => env('VIU_API_URL', ''),
        'api_key'     => env('VIU_API_KEY', ''),
        'timeout'     => (int) env('VIU_HTTP_TIMEOUT', 5),
        'max_retries' => (int) env('VIU_HTTP_MAX_RETRIES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuração Kafka
    |--------------------------------------------------------------------------
    | Usada quando transport_mode = 'kafka'.
    | Requer: composer require longlang/phpkafka
    */
    'kafka' => [
        'brokers'           => env('VIU_KAFKA_BROKERS', 'localhost:9092'),
        'topic'             => env('VIU_KAFKA_TOPIC', 'logs.app.raw'),
        'username'          => env('VIU_KAFKA_USERNAME', ''),
        'password'          => env('VIU_KAFKA_PASSWORD', ''),
        'sasl_mechanism'    => env('VIU_KAFKA_SASL_MECHANISM', 'SCRAM-SHA-256'),
        'security_protocol' => env('VIU_KAFKA_SECURITY_PROTOCOL', 'SASL_SSL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batching
    |--------------------------------------------------------------------------
    | Número de logs acumulados antes de enviar em lote.
    | Logs pendentes são enviados automaticamente ao final da requisição.
    */
    'batch_size' => (int) env('VIU_BATCH_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Nível de Log mínimo
    |--------------------------------------------------------------------------
    | Logs abaixo deste nível serão ignorados.
    | Opções: debug, info, notice, warning, error, critical, alert, emergency
    */
    'level' => env('VIU_LOG_LEVEL', 'debug'),

    /*
    |--------------------------------------------------------------------------
    | Header de Correlation ID
    |--------------------------------------------------------------------------
    | Nome do header HTTP usado para propagação do Correlation ID.
    */
    'correlation_id_header' => env('VIU_CORRELATION_ID_HEADER', 'X-Correlation-ID'),

];
