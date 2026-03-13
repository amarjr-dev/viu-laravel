# viu-laravel

<div align="center">

[![Packagist Version](https://img.shields.io/packagist/v/viu/viu-laravel.svg)](https://packagist.org/packages/viu/viu-laravel)
[![PHP Version](https://img.shields.io/packagist/php-v/viu/viu-laravel.svg)](https://packagist.org/packages/viu/viu-laravel)
[![License](https://img.shields.io/github/license/viu-team/viu)](https://github.com/viu-team/viu/blob/main/LICENSE)

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-10%2F11%2F12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Monolog](https://img.shields.io/badge/Monolog-3.x-00A98F?style=for-the-badge&logo=php&logoColor=white)

</div>

**Laravel SDK para o sistema Viu de centralização de logs**

## ✨ Quer ver logs? → Joga no Viu. Viu?

`viu-laravel` integra o sistema de logging nativo do Laravel com a plataforma Viu, oferecendo:

- **Monolog Handler** — integração nativa via `config/logging.php`
- **Facade** — acesso direto via `Viu::info()`, `Viu::error()`, etc.
- **Middleware** — propagação automática de Correlation ID
- **HTTP Transport** (recomendado) — via Guzzle com retry + circuit breaker
- **Kafka Transport** (alternativo) — envio direto ao tópico Kafka

### 🚀 Features

- ✅ **Monolog Handler** — plug-and-play com `Log::channel('viu')`
- ✅ **Auto-discovery** — zero configuração manual de providers
- ✅ **Circuit Breaker** — previne connection storms
- ✅ **Retry automático** — backoff exponencial em 429/5xx
- ✅ **Smart Batching** — envia em lote, flush automático no shutdown
- ✅ **Correlation IDs** — rastreamento de requisições ponta-a-ponta
- ✅ **PHP 8.1+ / Laravel 10/11/12**
- ✅ **Kafka support** — via `longlang/phpkafka` (pure PHP, sem extensão C)

---

## 📦 Instalação

```bash
composer require viu/viu-laravel
```

> O service provider é registrado automaticamente via auto-discovery do Laravel.

**Opcional — Transport Kafka:**
```bash
composer require longlang/phpkafka
```

---

## ⚙️ Configuração

### 1. Publicar o arquivo de configuração

```bash
php artisan vendor:publish --tag=viu-config
```

Isso cria `config/viu.php` no seu projeto.

### 2. Variáveis de ambiente (`.env`)

```dotenv
VIU_SERVICE_NAME=my-laravel-app
VIU_ENVIRONMENT=production
VIU_API_URL=https://api.viu.com
VIU_API_KEY=viu_live_xxxxxxxxxxxxxxxxxxxxxxxx

# Opcional
VIU_LOG_LEVEL=debug
VIU_BATCH_SIZE=100
VIU_HTTP_TIMEOUT=5
VIU_HTTP_MAX_RETRIES=3
```

---

## 🎯 Formas de uso

### 1. Canal Monolog (recomendado)

Adicione o canal `viu` em `config/logging.php`:

```php
'channels' => [
    // Canal dedicado
    'viu' => [
        'driver' => 'viu',
    ],

    // Stack combinando logs locais + Viu
    'stack' => [
        'driver'   => 'stack',
        'channels' => ['daily', 'viu'],
    ],
],
```

Uso em código:
```php
use Illuminate\Support\Facades\Log;

Log::channel('viu')->info('User logged in', ['user_id' => 123]);
Log::channel('viu')->error('Payment failed', ['amount' => 99.90]);

// Ou via stack (envia para 'daily' e 'viu' simultaneamente)
Log::stack(['daily', 'viu'])->warning('Rate limit approaching');
```

Sobrescrever o nível mínimo por canal:
```php
'viu' => [
    'driver' => 'viu',
    'level'  => 'warning', // ignora debug e info neste canal
],
```

---

### 2. Facade Viu

```php
use Viu\ViuLaravel\Facades\Viu;

Viu::info('User logged in', ['user_id' => 123]);
Viu::warning('Rate limit approaching', ['remaining' => 10]);
Viu::error('Payment failed', ['amount' => 99.90, 'currency' => 'BRL']);
Viu::critical('Database unreachable', ['host' => 'db.prod']);

// Com exception
try {
    processPayment($order);
} catch (\Throwable $e) {
    Viu::error('Payment processing failed', [
        'exception' => $e,
        'order_id'  => $order->id,
        'amount'    => $order->total,
    ]);
}
```

---

### 3. Injeção de dependência

```php
use Viu\ViuLaravel\ViuLogger;

class OrderService
{
    public function __construct(
        private readonly ViuLogger $logger,
    ) {}

    public function processOrder(Order $order): void
    {
        $this->logger->info('Processing order', ['order_id' => $order->id]);

        try {
            // ...
        } catch (\Throwable $e) {
            $this->logger->error('Order failed', [
                'exception' => $e,
                'order_id'  => $order->id,
            ]);
        }
    }
}
```

---

## 🔗 Middleware — Correlation ID

O `ViuCorrelationMiddleware` propaga automaticamente o `Correlation ID` e `Trace ID` entre requisições,
garantindo rastreabilidade ponta-a-ponta nos logs.

### Laravel 10 — `app/Http/Kernel.php`

```php
protected $middlewareGroups = [
    'api' => [
        \Viu\ViuLaravel\Middleware\ViuCorrelationMiddleware::class,
        // ...
    ],
    'web' => [
        \Viu\ViuLaravel\Middleware\ViuCorrelationMiddleware::class,
        // ...
    ],
];
```

### Laravel 11/12 — `bootstrap/app.php`

```php
use Viu\ViuLaravel\Middleware\ViuCorrelationMiddleware;

->withMiddleware(function (Middleware $middleware) {
    $middleware->appendToGroup('api', ViuCorrelationMiddleware::class);
    $middleware->appendToGroup('web', ViuCorrelationMiddleware::class);
})
```

### Comportamento

| Cenário | Comportamento |
|---------|---------------|
| Header `X-Correlation-ID` presente | Usa o valor recebido |
| Header ausente | Gera um novo UUID v4 |
| Header `X-Trace-ID` presente | Usa como Trace ID |
| `X-Trace-ID` ausente | Usa o Correlation ID como Trace ID |

Headers adicionados na response:
- `X-Correlation-ID`
- `X-Trace-ID`

---

## 🚀 Modo Kafka (alternativo)

Para ambientes de alta performance onde o envio direto ao Kafka é desejado:

```bash
composer require longlang/phpkafka
```

```dotenv
VIU_TRANSPORT_MODE=kafka
VIU_KAFKA_BROKERS=kafka.example.com:9092
VIU_KAFKA_TOPIC=logs.my-tenant
VIU_KAFKA_USERNAME=tenant_user
VIU_KAFKA_PASSWORD=secure-password
VIU_KAFKA_SASL_MECHANISM=SCRAM-SHA-256
VIU_KAFKA_SECURITY_PROTOCOL=SASL_SSL
```

> O modo HTTP é recomendado para a maioria dos casos por sua simplicidade e segurança (não expõe Kafka diretamente).

---

## 📋 Configuração completa

```php
// config/viu.php
return [
    'service_name'          => env('VIU_SERVICE_NAME', env('APP_NAME', 'laravel-app')),
    'environment'           => env('VIU_ENVIRONMENT', env('APP_ENV', 'production')),
    'transport_mode'        => env('VIU_TRANSPORT_MODE', 'http'), // 'http' | 'kafka'

    'http' => [
        'api_url'     => env('VIU_API_URL', ''),
        'api_key'     => env('VIU_API_KEY', ''),
        'timeout'     => (int) env('VIU_HTTP_TIMEOUT', 5),
        'max_retries' => (int) env('VIU_HTTP_MAX_RETRIES', 3),
    ],

    'kafka' => [
        'brokers'           => env('VIU_KAFKA_BROKERS', 'localhost:9092'),
        'topic'             => env('VIU_KAFKA_TOPIC', 'logs.app.raw'),
        'username'          => env('VIU_KAFKA_USERNAME', ''),
        'password'          => env('VIU_KAFKA_PASSWORD', ''),
        'sasl_mechanism'    => env('VIU_KAFKA_SASL_MECHANISM', 'SCRAM-SHA-256'),
        'security_protocol' => env('VIU_KAFKA_SECURITY_PROTOCOL', 'SASL_SSL'),
    ],

    'batch_size'           => (int) env('VIU_BATCH_SIZE', 100),
    'level'                => env('VIU_LOG_LEVEL', 'debug'),
    'correlation_id_header' => env('VIU_CORRELATION_ID_HEADER', 'X-Correlation-ID'),
];
```

---

## 🧩 Payload enviado ao backend

```json
{
    "timestamp": "2026-03-13T15:04:05.000+00:00",
    "level": "ERROR",
    "message": "Payment failed",
    "service": "my-laravel-app",
    "environment": "production",
    "source": "payments",
    "correlation_id": "550e8400-e29b-41d4-a716-446655440000",
    "trace_id": "550e8400-e29b-41d4-a716-446655440000",
    "span_id": "a1b2c3d4e5f6g7h8",
    "module": "payments",
    "file": "",
    "line": 0,
    "context": {
        "amount": 99.90,
        "currency": "BRL",
        "exception": {
            "type": "RuntimeException",
            "message": "Insufficient funds",
            "code": 0,
            "file": "/app/Services/PaymentService.php",
            "line": 42,
            "stacktrace": "..."
        }
    }
}
```

---

## 🧪 Flush manual (queue workers / jobs)

Em processos de longa duração (queue workers, Octane, etc.), chame `flush()` após cada job para garantir o envio dos logs pendentes:

```php
// Em um Job
public function handle(ViuLogger $logger): void
{
    $logger->info('Job started', ['job' => static::class]);
    // ...
    $logger->info('Job completed');
    $logger->flush(); // envia imediatamente
}
```

Via Facade:
```php
Viu::info('Job done');
Viu::flush();
```

---

## 🧪 Testes

```bash
composer install
./vendor/bin/phpunit
```

---

## 📄 Licença

MIT — veja [LICENSE](LICENSE).
