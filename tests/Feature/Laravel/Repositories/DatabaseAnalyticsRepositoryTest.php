<?php

declare(strict_types=1);

use Denosys\Analytics\Contracts\AnalyticsRepository;
use Denosys\Analytics\Enums\EventType;
use Denosys\Analytics\PanConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('routes queries through the configured database connection', function (): void {
    config(['database.connections.secondary' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]]);

    Schema::connection('secondary')->create('pan_analytics', function ($table): void {
        $table->id();
        $table->string('name');
        $table->unsignedBigInteger('impressions')->default(0);
        $table->unsignedBigInteger('hovers')->default(0);
        $table->unsignedBigInteger('clicks')->default(0);
    });

    PanConfiguration::databaseConnection('secondary');

    app(AnalyticsRepository::class)->increment('help-modal', EventType::CLICK);

    expect(DB::connection('secondary')->table('pan_analytics')->count())->toBe(1)
        ->and(DB::table('pan_analytics')->count())->toBe(0);
})->after(function (): void {
    DB::purge('secondary');
    PanConfiguration::databaseConnection(config('database.default'));
});

it('collapses a batch of many same-name events into a bounded number of queries', function (): void {
    $repository = app(AnalyticsRepository::class);

    $increments = [];
    for ($i = 0; $i < 100; $i++) {
        $increments[] = ['name' => 'hero-cta', 'event' => EventType::IMPRESSION];
    }

    DB::enableQueryLog();
    $repository->batchIncrement($increments);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // 1 SELECT for existing rows + 1 COUNT for the max cap + 1 INSERT for the
    // single new row. Bounded regardless of batch size (used to be 200+).
    expect(count($queries))->toBeLessThanOrEqual(3);
    expect((int) DB::table('pan_analytics')->where('name', 'hero-cta')->value('impressions'))->toBe(100);
});

it('aggregates mixed event types for the same name in a single update', function (): void {
    $repository = app(AnalyticsRepository::class);

    DB::table('pan_analytics')->insert([
        'name' => 'signup-btn',
        'impressions' => 0,
        'hovers' => 0,
        'clicks' => 0,
    ]);

    $repository->batchIncrement([
        ['name' => 'signup-btn', 'event' => EventType::IMPRESSION],
        ['name' => 'signup-btn', 'event' => EventType::IMPRESSION],
        ['name' => 'signup-btn', 'event' => EventType::IMPRESSION],
        ['name' => 'signup-btn', 'event' => EventType::HOVER],
        ['name' => 'signup-btn', 'event' => EventType::HOVER],
        ['name' => 'signup-btn', 'event' => EventType::CLICK],
    ]);

    $row = DB::table('pan_analytics')->where('name', 'signup-btn')->first();
    expect($row)->not->toBeNull()
        ->and((int) $row->impressions)->toBe(3)
        ->and((int) $row->hovers)->toBe(2)
        ->and((int) $row->clicks)->toBe(1);
});

it('respects the allowed_analytics allowlist during batch increment', function (): void {
    PanConfiguration::allowedAnalytics(['allowed-event']);

    try {
        app(AnalyticsRepository::class)->batchIncrement([
            ['name' => 'allowed-event', 'event' => EventType::IMPRESSION],
            ['name' => 'rejected-event', 'event' => EventType::IMPRESSION],
        ]);

        expect(DB::table('pan_analytics')->where('name', 'allowed-event')->exists())->toBeTrue();
        expect(DB::table('pan_analytics')->where('name', 'rejected-event')->exists())->toBeFalse();
    } finally {
        PanConfiguration::allowedAnalytics([]);
    }
});

it('respects the max_analytics cap when batch-inserting new names', function (): void {
    PanConfiguration::maxAnalytics(2);

    try {
        app(AnalyticsRepository::class)->batchIncrement([
            ['name' => 'a', 'event' => EventType::IMPRESSION],
            ['name' => 'b', 'event' => EventType::IMPRESSION],
            ['name' => 'c', 'event' => EventType::IMPRESSION],
        ]);

        expect(DB::table('pan_analytics')->count())->toBe(2);
    } finally {
        PanConfiguration::maxAnalytics(50);
    }
});

it('is a no-op when given an empty batch', function (): void {
    DB::enableQueryLog();
    app(AnalyticsRepository::class)->batchIncrement([]);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});
