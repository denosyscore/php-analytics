<?php

declare(strict_types=1);

namespace Denosys\Analytics\Adapters\Laravel\Http\Controllers;

use Denosys\Analytics\Actions\CreateEvent;
use Denosys\Analytics\Adapters\Laravel\Http\Requests\CreateEventRequest;
use Illuminate\Http\Response;

/**
 * @internal
 */
final readonly class EventController
{
    /**
     * Store a new event batch. Delegates to CreateEvent::handleBatch so the
     * aggregated SQL path is the one used regardless of how many events the
     * client submits in the request.
     */
    public function store(CreateEventRequest $request, CreateEvent $action): Response
    {
        /** @var array<int, array{name: string, type: string}> $events */
        $events = $request->collect('events')->all();

        $action->handleBatch($events);

        return response()->noContent();
    }
}
