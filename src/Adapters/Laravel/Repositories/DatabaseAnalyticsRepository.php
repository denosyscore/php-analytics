<?php

declare(strict_types=1);

namespace Denosys\Analytics\Adapters\Laravel\Repositories;

use Denosys\Analytics\Contracts\AnalyticsRepository;
use Denosys\Analytics\Enums\EventType;
use Denosys\Analytics\PanConfiguration;
use Denosys\Analytics\ValueObjects\Analytic;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;

/**
 * @internal
 */
final readonly class DatabaseAnalyticsRepository implements AnalyticsRepository
{
    public function __construct(
        private DatabaseManager $databaseManager,
        private PanConfiguration $config,
    ) {}

    /**
     * Returns all analytics.
     *
     * @return array<int, Analytic>
     */
    public function all(): array
    {
        /** @var array<int, Analytic> $all */
        $all = $this->connection()->table('pan_analytics')->get()->map(fn (mixed $analytic): Analytic => new Analytic(
            id: (int) $analytic->id,
            name: $analytic->name,
            impressions: (int) $analytic->impressions,
            hovers: (int) $analytic->hovers,
            clicks: (int) $analytic->clicks,
        ))->toArray();

        return $all;
    }

    /**
     * Increment a single event. Delegates to batchIncrement so the aggregated
     * SQL path is the one-and-only write path — avoids drift between the
     * single-event and multi-event implementations.
     */
    public function increment(string $name, EventType $event): void
    {
        $this->batchIncrement([['name' => $name, 'event' => $event]]);
    }

    /**
     * Increment a batch of events with a bounded number of queries:
     *   - 1 SELECT to find existing rows
     *   - 1 UPDATE per unique existing name (N+1 against *unique names*, which
     *     is tiny in practice, not against the event batch size)
     *   - 1 COUNT for the max_analytics cap check
     *   - 1 bulk INSERT for new names
     *
     * @param  array<int, array{name: string, event: EventType}>  $increments
     */
    public function batchIncrement(array $increments): void
    {
        if ($increments === []) {
            return;
        }

        [
            'allowed_analytics' => $allowedAnalytics,
            'max_analytics' => $maxAnalytics,
        ] = $this->config->toArray();

        $hasAllowlist = count($allowedAnalytics) > 0;

        /** @var array<string, array<string, int>> $buffer */
        $buffer = [];
        foreach ($increments as $increment) {
            $name = $increment['name'];

            if ($hasAllowlist && ! in_array($name, $allowedAnalytics, true)) {
                continue;
            }

            $column = $increment['event']->column();
            $buffer[$name][$column] = ($buffer[$name][$column] ?? 0) + 1;
        }

        if ($buffer === []) {
            return;
        }

        $connection = $this->connection();

        $names = array_keys($buffer);
        $existingRows = $connection->table('pan_analytics')
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        $existingSet = [];
        foreach ($existingRows as $existingName) {
            if (is_string($existingName)) {
                $existingSet[$existingName] = true;
            }
        }

        $rowsToInsert = [];

        foreach ($buffer as $name => $columns) {
            if (isset($existingSet[$name])) {
                $connection->table('pan_analytics')
                    ->where('name', $name)
                    ->incrementEach($columns);

                continue;
            }

            $rowsToInsert[] = array_merge(
                ['name' => $name, 'impressions' => 0, 'hovers' => 0, 'clicks' => 0],
                $columns,
            );
        }

        if ($rowsToInsert === []) {
            return;
        }

        $currentCount = $connection->table('pan_analytics')->count();
        $remainingCapacity = max(0, $maxAnalytics - $currentCount);

        if ($remainingCapacity === 0) {
            return;
        }

        $connection->table('pan_analytics')->insert(
            array_slice($rowsToInsert, 0, $remainingCapacity),
        );
    }

    /**
     * Flush all analytics.
     */
    public function flush(): void
    {
        $this->connection()->table('pan_analytics')->truncate();
    }

    /**
     * Delete a specific analytic by ID.
     */
    public function delete(int $id): int
    {
        return $this->connection()->table('pan_analytics')->where('id', $id)->delete();
    }

    /**
     * Resolve the database connection.
     */
    private function connection(): Connection
    {
        return $this->databaseManager->connection($this->config->getDatabaseConnection());
    }
}
