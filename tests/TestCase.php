<?php

namespace Nevadskiy\Tokens\Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nevadskiy\Tokens\Tests\Support\Factory\TokenFactory;
use Nevadskiy\Tokens\Tests\Support\Models\User;
use Nevadskiy\Tokens\TokenManager;
use Nevadskiy\Tokens\TokenServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'APP_KEY']);

        $this->loadMigrationsFrom(__DIR__.'/Support/Database/Migrations');

        $this->withFactories(__DIR__.'/Support/Database/Factory');

        $this->artisan('migrate', ['--database' => 'testbench'])->run();
    }
    /**
     * Get package providers.
     *
     * @param Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [TokenServiceProvider::class];
    }
    /**
     * Define environment setup.
     *
     * @param Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');

        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * Freeze time for tests.
     *
     * @param Carbon|null $time
     * @return Carbon
     */
    protected function freezeTime(Carbon $time = null): Carbon
    {
        // Allows to use this time when comparing with database time.
        $time = Carbon::createFromTimestamp(
            ($time ?: Carbon::now())->getTimestamp()
        );

        Carbon::setTestNow($time);

        return $time;
    }

    /**
     * Create the tokenable entity.
     *
     * @return mixed
     */
    protected function createTokenableEntity()
    {
        return factory(User::class)->create();
    }

    /**
     * Get the token factory instance.
     *
     * @return TokenFactory
     */
    protected function tokenFactory(): TokenFactory
    {
        return app(TokenFactory::class);
    }

    /**
     * Get the token manager.
     *
     * @return TokenManager
     */
    protected function tokenManager(): TokenManager
    {
        return app(TokenManager::class);
    }
}
