<?php

declare(strict_types=1);

namespace Viu\ViuLaravel\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Viu\ViuLaravel\ViuLogger;

/**
 * Middleware para propagação automática de Correlation ID e Trace ID.
 *
 * Comportamento:
 *   - Lê o Correlation ID do header configurado (padrão: X-Correlation-ID)
 *   - Se ausente, gera um novo UUID v4
 *   - Propaga o ID para o ViuLogger (presente em todos os logs do request)
 *   - Adiciona os headers X-Correlation-ID e X-Trace-ID na response
 *
 * Registro (Laravel 10 — app/Http/Kernel.php):
 *   protected $middlewareGroups = [
 *       'api' => [
 *           \Viu\ViuLaravel\Middleware\ViuCorrelationMiddleware::class,
 *       ],
 *   ];
 *
 * Registro (Laravel 11/12 — bootstrap/app.php):
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->appendToGroup('api', ViuCorrelationMiddleware::class);
 *   })
 */
class ViuCorrelationMiddleware
{
    public function __construct(
        private readonly ViuLogger $logger,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $headerName    = $this->logger->getConfig()->correlationIdHeader;
        $correlationId = $request->header($headerName) ?: $this->generateUuid();
        $traceId       = $request->header('X-Trace-ID') ?: $correlationId;
        $spanId        = substr($this->generateUuid(), 0, 16);

        $this->logger->setCorrelationId($correlationId);
        $this->logger->setTraceId($traceId);
        $this->logger->setSpanId($spanId);

        // Garante que o header esteja disponível no request para código downstream
        $request->headers->set($headerName, $correlationId);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set($headerName, $correlationId);
        $response->headers->set('X-Trace-ID', $traceId);

        return $response;
    }

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
