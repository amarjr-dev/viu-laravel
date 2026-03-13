<?php

declare(strict_types=1);

namespace Viu\ViuLaravel\Support;

/**
 * Circuit Breaker para proteger chamadas ao backend Viu.
 *
 * Estados:
 *   - closed    — operação normal, requisições permitidas
 *   - open      — falhas acima do threshold, requisições bloqueadas
 *   - half-open — após timeout, permite uma tentativa de recuperação
 */
final class CircuitBreaker
{
    private int $failures = 0;
    private ?int $lastFailureTime = null;
    private string $state = 'closed';

    public function __construct(
        private readonly int $failureThreshold = 5,
        private readonly int $timeout = 60,
    ) {}

    /**
     * Retorna se uma tentativa é permitida no estado atual.
     */
    public function canAttempt(): bool
    {
        if ($this->state === 'closed') {
            return true;
        }

        if ($this->state === 'open') {
            if ($this->lastFailureTime !== null
                && (time() - $this->lastFailureTime) > $this->timeout
            ) {
                $this->state = 'half-open';
                return true;
            }
            return false;
        }

        // half-open: permite a tentativa
        return true;
    }

    /**
     * Registra uma operação bem-sucedida e fecha o circuito.
     */
    public function recordSuccess(): void
    {
        $this->failures = 0;
        $this->state = 'closed';
        $this->lastFailureTime = null;
    }

    /**
     * Registra uma falha; abre o circuito após atingir o threshold.
     */
    public function recordFailure(): void
    {
        $this->failures++;
        $this->lastFailureTime = time();

        if ($this->failures >= $this->failureThreshold) {
            $this->state = 'open';
        }
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getFailures(): int
    {
        return $this->failures;
    }
}
