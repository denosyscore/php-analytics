<?php

use Denosys\Analytics\Actions\CreateEvent;
use Denosys\Analytics\Contracts\AnalyticsRepository;
use Denosys\Analytics\Enums\EventType;
use Denosys\Analytics\ValueObjects\Analytic;

it('increments the click event for the given analytic', function (): void {
    $action = app(CreateEvent::class);

    $action->handle('help-modal', EventType::CLICK);
    $action->handle('help-modal', EventType::CLICK);
    $action->handle('help-modal', EventType::HOVER);

    $analytics = array_map(fn (Analytic $analytic): array => $analytic->toArray(), app(AnalyticsRepository::class)->all());

    expect($analytics)->toBe([
        ['id' => 1, 'name' => 'help-modal', 'impressions' => 0, 'hovers' => 1, 'clicks' => 2],
    ]);
});
