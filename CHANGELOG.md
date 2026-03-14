# Changelog

All notable changes to `viu-laravel` will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-03-14

### Fixed

- **ViuMonologHandler** — adicionado `IntrospectionProcessor` para capturar automaticamente `file` e `line` de onde o log foi chamado na aplicação, ignorando internals do SDK, Monolog e Laravel. Resolve logs chegando com `file: ""` e `line: 0`.

## [0.1.0] - 2026-03-13

### Added

- **ViuMonologHandler** — Monolog 3 handler com suporte a batching, flush automático via shutdown function e extração de contexto de exceções
- **ViuLogger** — logger standalone com métodos PSR-3 (`debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`) e controle de Correlation/Trace/Span ID
- **Facade Viu** — acesso estático via `Viu::info()`, `Viu::error()`, etc.
- **ViuServiceProvider** — auto-registration via Composer, singleton `'viu'`, publicação de config e driver `'viu'` para `Log::channel('viu')`
- **ViuCorrelationMiddleware** — propagação de Correlation ID e Trace ID; gera UUID v4 quando ausente; adiciona headers na response
- **HttpClient** — Guzzle 7 com retry exponencial (429/5xx) e circuit breaker
- **KafkaProducer** — suporte ao modo Kafka via `longlang/phpkafka` (opcional)
- **CircuitBreaker** — estados `closed/open/half-open`, threshold e timeout configuráveis
- **ViuConfig** — value object imutável com `fromArray()` e `fromEnv()`
- Suporte a **PHP 8.1+** e **Laravel 10 / 11 / 12**
- Testes PHPUnit cobrindo: CircuitBreaker, HttpClient, ViuConfig, ViuMonologHandler, ViuCorrelationMiddleware

[Unreleased]: https://github.com/viu-team/viu/compare/viu-laravel-v0.1.0...HEAD
[0.1.0]: https://github.com/viu-team/viu/releases/tag/viu-laravel-v0.1.0
