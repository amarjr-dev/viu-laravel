<?php

declare(strict_types=1);

namespace Viu\ViuLaravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade para acesso estático ao ViuLogger.
 *
 * @method static void debug(string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void notice(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void critical(string $message, array $context = [])
 * @method static void alert(string $message, array $context = [])
 * @method static void emergency(string $message, array $context = [])
 * @method static void setCorrelationId(?string $correlationId)
 * @method static void setTraceId(?string $traceId)
 * @method static void setSpanId(?string $spanId)
 * @method static void flush()
 * @method static \Viu\ViuLaravel\ViuConfig getConfig()
 * @method static \Viu\ViuLaravel\Handlers\ViuMonologHandler getHandler()
 *
 * @see \Viu\ViuLaravel\ViuLogger
 */
class Viu extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'viu';
    }
}
