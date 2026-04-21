<?php

declare(strict_types=1);

namespace Denosys\Analytics\Actions;

use Denosys\Analytics\Contracts\AnalyticsRepository;
use Denosys\Analytics\Enums\EventType;

/**
 * @internal
 */
final readonly class CreateEvent
{
    public function __construct(
        private AnalyticsRepository $repository,
    ) {}

    /**
     * Record a single event.
     */
    public function handle(string $name, EventType $event): void
    {
        $this->repository->increment($name, $event);
    }

    /**
     * Record a batch of events in aggregate. Prefer this entry point when a
     * request carries more than one event — it lets the repository collapse
     * duplicates into a bounded number of SQL statements rather than one
     * SELECT/UPDATE round-trip per event.
     *
     * @param  array<int, array{name: string, type: string}>  $events
     */
    public function handleBatch(array $events): void
    {
        $increments = [];
        foreach ($events as $event) {
            $increments[] = [
                'name' => $event['name'],
                'event' => EventType::from($event['type']),
            ];
        }

        $this->repository->batchIncrement($increments);
    }
}
