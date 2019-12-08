<?php

namespace Nevadskiy\Tokens;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Nevadskiy\Tokens\Generator\RandomHashGenerator;
use Nevadskiy\Tokens\RateLimiter;
use Nevadskiy\Tokens\Tokens\OptionsToken;
use Nevadskiy\Tokens\Console;

class TokenServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerConfig();
        $this->registerDependencies();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->bootMigrations();
        $this->bootPublishable();
        $this->bootCommands();
    }

    /**
     * Register any application configurations.
     */
    private function registerConfig(): void
    {
        $this->mergeConfigFrom($this->getConfigPath(), 'tokens');
    }

    /**
     * Register any application dependencies.
     */
    private function registerDependencies(): void
    {
        $this->app->singleton(TokenManager::class);

        $this->app->afterResolving(TokenManager::class, function (TokenManager $manager) {
            foreach ($this->app['config']['tokens']['define'] as $token => $options) {
                if (is_int($token)) {
                    $manager->define($options);
                } else {
                    $manager->define($token, $options);
                }
            }
        });

        $this->app->singletonIf(RateLimiter\RateLimiter::class, RateLimiter\CacheRateLimiter::class);

        $this->app->singletonIf(RandomHashGenerator::class, function (Application $app) {
            return new RandomHashGenerator($app['config']['app']['key']);
        });

        $this->app->when(OptionsToken::class)->needs('$defaults')->give(function (Application $app) {
            return $app['config']['tokens']['defaults'];
        });
    }

    /**
     * Boot any application migrations.
     */
    private function bootMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../migrations');
    }

    /**
     * Boot any application publishable resources.
     */
    private function bootPublishable(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([$this->getConfigPath() => config_path('tokens.php')], 'tokens-config');
        }
    }

    /**
     * Get the application config file path.
     *
     * @return string
     */
    private function getConfigPath(): string
    {
        return __DIR__ . '/../config/tokens.php';
    }

    /**
     * Boot any application commands.
     */
    private function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\ClearCommand::class,
            ]);
        }
    }
}
