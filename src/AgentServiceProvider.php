<?php
declare(strict_types=1);

namespace Aerobit\OpenaiAgents;

use Illuminate\Support\ServiceProvider;
use Aerobit\OpenaiAgents\Tracing\Tracing;

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

        $this->app->singleton(Tracing::class, function ($app) {
            $config = $app['config']['agents.tracing'];
            if (!($config['enabled'] ?? false)) {
                return new Tracing();
            }
            return new Tracing($config['processors'] ?? []);
        });
    }
}
