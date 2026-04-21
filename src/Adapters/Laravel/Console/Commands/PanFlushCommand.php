<?php

declare(strict_types=1);

namespace Denosys\Analytics\Adapters\Laravel\Console\Commands;

use Denosys\Analytics\Contracts\AnalyticsRepository;
use Illuminate\Console\Command;

/**
 * @internal
 */
final class PanFlushCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pan:flush';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Flush all your analytics';

    /**
     * Execute the console command.
     */
    public function handle(AnalyticsRepository $analytics): void
    {
        $analytics->flush();

        $this->components->info('All analytics have been flushed.');
    }
}
