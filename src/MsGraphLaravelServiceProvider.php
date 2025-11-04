<?php

namespace Hwkdo\MsGraphLaravel;

use Hwkdo\MsGraphLaravel\Commands\checkSubscriptions;
use Hwkdo\MsGraphLaravel\Commands\refreshAktivUsersWithOooCache;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MsGraphLaravelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('ms-graph-laravel')
            ->hasConfigFile()
            ->hasCommands([
                checkSubscriptions::class,
                refreshAktivUsersWithOooCache::class,
            ])
            ->discoversMigrations();
    }

    public function boot(): void
    {
        parent::boot();
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
    }

    public function packageRegistered(): void
    {
        // Register services as singletons
        $this->app->singleton(MsGraphLaravel::class, function ($app) {
            return new MsGraphLaravel;
        });

        // Register service bindings
        $this->app->bind(
            \Hwkdo\MsGraphLaravel\Interfaces\MsGraphAuthenticationServiceInterface::class,
            \Hwkdo\MsGraphLaravel\Services\AuthenticationService::class
        );

        $this->app->bind(
            \Hwkdo\MsGraphLaravel\Interfaces\MsGraphDateTimeServiceInterface::class,
            \Hwkdo\MsGraphLaravel\Services\DateTimeService::class
        );

        $this->app->bind(
            \Hwkdo\MsGraphLaravel\Interfaces\MsGraphLicenseServiceInterface::class,
            \Hwkdo\MsGraphLaravel\Services\LicenseService::class
        );

        $this->app->bind(
            \Hwkdo\MsGraphLaravel\Interfaces\MsGraphMailboxServiceInterface::class,
            \Hwkdo\MsGraphLaravel\Services\MailboxService::class
        );

        $this->app->bind(
            \Hwkdo\MsGraphLaravel\Interfaces\MsGraphMailServiceInterface::class,
            \Hwkdo\MsGraphLaravel\Services\MailService::class
        );

        $this->app->bind(
            \Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface::class,
            \Hwkdo\MsGraphLaravel\Services\UserService::class
        );

        $this->app->bind(
            \Hwkdo\MsGraphLaravel\Interfaces\MsGraphOneDriveServiceInterface::class,
            \Hwkdo\MsGraphLaravel\Services\OneDriveService::class
        );

        $this->app->bind(
            \Hwkdo\MsGraphLaravel\Interfaces\MsGraphOutOfOfficeTemplateServiceInterface::class,
            \Hwkdo\MsGraphLaravel\Services\OutOfOfficeTemplateService::class
        );
    }
}
