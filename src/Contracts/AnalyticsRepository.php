<?php

declare(strict_types=1);

namespace Denosys\Analytics\Contracts;

use Denosys\Analytics\Enums\EventType;
use Denosys\Analytics\ValueObjects\Analytic;

interface AnalyticsRepository
{
    /**
     * Returns all analytics.
     *
     * @return array<int, Analytic>
     */
    public function all(): array;

    /**
     * Increment a single event for the given analytic.
     */
    public function increment(string $name, EventType $event): void;

    /**
     * Increment a batch of events in aggregate.
     *
     * Implementations should collapse duplicates (same name + same event)
     * into aggregated database writes and issue a bounded number of queries
     * regardless of input size. This is the preferred entry point whenever a
     * caller has more than one event to record in a single request cycle —
     * the default `increment()` path calls this under the hood with a
     * single-element array.
     *
     * @param  array<int, array{name: string, event: EventType}>  $increments
     */
    public function batchIncrement(array $increments): void;

    /**
     * Flush all analytics.
     */
    public function flush(): void;

    /**
     * Delete a specific analytic by ID.
     */
    public function delete(int $id): int;
}
