<?php

declare(strict_types=1);

namespace Denosys\Analytics\Adapters\Laravel\Providers;

use Denosys\Analytics\Adapters\Laravel\Console\Commands\InstallPanCommand;
use Denosys\Analytics\Adapters\Laravel\Console\Commands\PanCommand;
use Denosys\Analytics\Adapters\Laravel\Console\Commands\PanDeleteCommand;
use Denosys\Analytics\Adapters\Laravel\Console\Commands\PanFlushCommand;
use Denosys\Analytics\Adapters\Laravel\Http\Controllers\EventController;
use Denosys\Analytics\Adapters\Laravel\Http\Middleware\InjectJavascriptLibrary;
use Denosys\Analytics\Adapters\Laravel\Repositories\DatabaseAnalyticsRepository;
use Denosys\Analytics\Contracts\AnalyticsRepository;
use Denosys\Analytics\PanConfiguration;
use Illuminate\Contracts\Http\Kernel as HttpContract;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * @internal
 */
final class PanServiceProvider extends ServiceProvider
{
    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->registerConfiguration();
        $this->registerRepositories();
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->registerCommands();
        $this->registerRoutes();
        $this->registerPublishing();
    }

    /**
     * Register the package configuration.
     */
    private function registerConfiguration(): void
    {
        $this->app->bind(PanConfiguration::class, fn (): PanConfiguration => PanConfiguration::instance());
    }

    /**
     * Register the package repositories.
     */
    private function registerRepositories(): void
    {
        $this->app->bind(AnalyticsRepository::class, DatabaseAnalyticsRepository::class);
    }

    /**
     * Register the package routes.
     */
    private function registerRoutes(): void
    {
        /** @var Kernel $kernel */
        $kernel = $this->app->make(HttpContract::class);

        $kernel->pushMiddleware(InjectJavascriptLibrary::class);

        /** @var PanConfiguration $config */
        $config = $this->app->get(PanConfiguration::class);

        Route::prefix($config->toArray()['route_prefix'])->group(function (): void {
            Route::post('/events', [EventController::class, 'store']);
        });
    }

    /**
     * Register the package's publishable resources.
     */
    private function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishesMigrations([
                __DIR__.'/../../../../database/migrations' => database_path('migrations'),
            ], 'pan-migrations');
        }
    }

    /**
     * Register the package's commands.
     */
    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallPanCommand::class,
                PanCommand::class,
                PanFlushCommand::class,
                PanDeleteCommand::class,
            ]);
        }
    }
}
