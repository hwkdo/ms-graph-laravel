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

        $app['config']->set('ms-graph-laravel.teams_bot.enabled', true);
        $app['config']->set('ms-graph-laravel.teams_bot.app_id', 'test-bot-app-id');
        $app['config']->set('ms-graph-laravel.teams_bot.app_secret', 'test-bot-secret');
        $app['config']->set('ms-graph-laravel.teams_bot.teams_app_id', 'test-teams-app-id');
        $app['config']->set('ms-graph-laravel.teams_sdk_rest.base_url', 'http://teams-sdk-rest.test');
        $app['config']->set('ms-graph-laravel.teams_sdk_rest.api_key', 'test-api-key');
        $app['config']->set('ms-graph-laravel.teams_activity_feed.enabled', true);
        $app['config']->set('ms-graph-laravel.teams_activity_feed.activity_type', 'systemDefault');
        $app['config']->set('ms-graph-laravel.teams_activity_feed.topic_title', 'Test Intranet');
    }
}
