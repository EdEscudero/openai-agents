<?php

namespace OpenAI\LaravelAgents;

use Illuminate\Support\ServiceProvider;

class AgentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/agents.php' => config_path('agents.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\ChatAgent::class,
            ]);
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/agents.php', 'agents');

        $this->app->singleton(AgentManager::class, function ($app) {
            return new AgentManager($app['config']['agents']);
        });
    }
}
