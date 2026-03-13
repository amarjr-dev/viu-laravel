<?php

declare(strict_types=1);

namespace Viu\ViuLaravel\Contracts;

/**
 * Contrato para transporte de logs ao backend Viu via HTTP.
 */
interface HttpClientInterface
{
    /**
     * Envia um único log entry.
     */
    public function send(array $logEntry): bool;

    /**
     * Envia múltiplos log entries em lote.
     */
    public function sendBatch(array $logEntries): bool;
}
