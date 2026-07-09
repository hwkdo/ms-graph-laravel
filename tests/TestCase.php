<?php

namespace Hwkdo\MsGraphLaravel\Tests;

use Hwkdo\MsGraphLaravel\MsGraphLaravelServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Hwkdo\\MsGraphLaravel\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            MsGraphLaravelServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        foreach (File::allFiles(__DIR__.'/../database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
        }
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('ms-graph-laravel.azure_app_registrations.teams_bot.client_id', 'test-bot-app-id');
        $app['config']->set('ms-graph-laravel.azure_app_registrations.teams_bot.client_secret', 'test-bot-secret');
        $app['config']->set('ms-graph-laravel.tenant_id', 'test-tenant-id');
    }
}
