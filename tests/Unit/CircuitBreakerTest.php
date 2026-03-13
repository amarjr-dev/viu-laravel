<?php

declare(strict_types=1);

namespace Viu\ViuLaravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Viu\ViuLaravel\Support\CircuitBreaker;

final class CircuitBreakerTest extends TestCase
{
    public function test_starts_in_closed_state(): void
    {
        $cb = new CircuitBreaker();

        $this->assertSame('closed', $cb->getState());
        $this->assertTrue($cb->canAttempt());
        $this->assertSame(0, $cb->getFailures());
    }

    public function test_stays_closed_below_threshold(): void
    {
        $cb = new CircuitBreaker(failureThreshold: 3);

        $cb->recordFailure();
        $this->assertSame('closed', $cb->getState());
        $this->assertTrue($cb->canAttempt());

        $cb->recordFailure();
        $this->assertSame('closed', $cb->getState());
        $this->assertTrue($cb->canAttempt());
    }

    public function test_opens_after_reaching_threshold(): void
    {
        $cb = new CircuitBreaker(failureThreshold: 3, timeout: 60);

        $cb->recordFailure();
        $cb->recordFailure();
        $cb->recordFailure();

        $this->assertSame('open', $cb->getState());
        $this->assertFalse($cb->canAttempt());
    }

    public function test_resets_to_closed_after_success(): void
    {
        $cb = new CircuitBreaker(failureThreshold: 2, timeout: 60);

        $cb->recordFailure();
        $cb->recordFailure();
        $this->assertSame('open', $cb->getState());

        $cb->recordSuccess();

        $this->assertSame('closed', $cb->getState());
        $this->assertSame(0, $cb->getFailures());
        $this->assertTrue($cb->canAttempt());
    }

    public function test_transitions_to_half_open_after_timeout(): void
    {
        // timeout = 0 significa que qualquer passagem de tempo já habilita half-open
        $cb = new CircuitBreaker(failureThreshold: 1, timeout: 0);

        $cb->recordFailure();
        $this->assertSame('open', $cb->getState());

        // Com timeout=0, (time() - lastFailureTime) > 0 já é verdadeiro
        sleep(1);

        $this->assertTrue($cb->canAttempt());
        $this->assertSame('half-open', $cb->getState());
    }

    public function test_records_correct_failure_count(): void
    {
        $cb = new CircuitBreaker(failureThreshold: 10);

        $cb->recordFailure();
        $cb->recordFailure();
        $cb->recordFailure();

        $this->assertSame(3, $cb->getFailures());
    }

    public function test_success_resets_failure_count(): void
    {
        $cb = new CircuitBreaker(failureThreshold: 10);

        $cb->recordFailure();
        $cb->recordFailure();
        $this->assertSame(2, $cb->getFailures());

        $cb->recordSuccess();
        $this->assertSame(0, $cb->getFailures());
    }
}
