<?php

namespace Hwkdo\MsGraphLaravel;

use Hwkdo\MsGraphLaravel\Commands\checkSubscriptions;
use Hwkdo\MsGraphLaravel\Commands\InstallTeamsBotForAllUsersCommand;
use Hwkdo\MsGraphLaravel\Commands\refreshAktivUsersWithOooCache;
use Hwkdo\MsGraphLaravel\Commands\SyncOutOfOfficeCommand;
use Hwkdo\MsGraphLaravel\Http\TeamsSdkRestClient;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphAppServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphAuthenticationServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphDateTimeServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphGroupServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphIntuneServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphLicenseServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphMailboxServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphMailServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOneDriveServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOutOfOfficeTemplateServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphShareServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphTeamsActivityFeedServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphTeamsBotServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;
use Hwkdo\MsGraphLaravel\Services\AppService;
use Hwkdo\MsGraphLaravel\Services\AuthenticationService;
use Hwkdo\MsGraphLaravel\Services\DateTimeService;
use Hwkdo\MsGraphLaravel\Services\GroupService;
use Hwkdo\MsGraphLaravel\Services\IntuneService;
use Hwkdo\MsGraphLaravel\Services\LicenseService;
use Hwkdo\MsGraphLaravel\Services\MailboxService;
use Hwkdo\MsGraphLaravel\Services\MailService;
use Hwkdo\MsGraphLaravel\Services\OneDriveService;
use Hwkdo\MsGraphLaravel\Services\OutOfOfficeTemplateService;
use Hwkdo\MsGraphLaravel\Services\ShareService;
use Hwkdo\MsGraphLaravel\Services\TeamsActivityFeedService;
use Hwkdo\MsGraphLaravel\Services\TeamsBotService;
use Hwkdo\MsGraphLaravel\Services\UserService;
use Illuminate\Console\Scheduling\Schedule;
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
                InstallTeamsBotForAllUsersCommand::class,
                refreshAktivUsersWithOooCache::class,
                SyncOutOfOfficeCommand::class,
            ])
            ->discoversMigrations();
    }

    public function boot(): void
    {
        parent::boot();
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        $this->app->resolving(Schedule::class, function (): void {
            require __DIR__.'/../routes/console.php';
        });
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
            MsGraphAuthenticationServiceInterface::class,
            AuthenticationService::class
        );

        $this->app->bind(
            MsGraphDateTimeServiceInterface::class,
            DateTimeService::class
        );

        $this->app->bind(
            MsGraphLicenseServiceInterface::class,
            LicenseService::class
        );

        $this->app->bind(
            MsGraphMailboxServiceInterface::class,
            MailboxService::class
        );

        $this->app->bind(
            MsGraphMailServiceInterface::class,
            MailService::class
        );

        $this->app->bind(
            MsGraphUserServiceInterface::class,
            UserService::class
        );

        $this->app->bind(
            MsGraphOneDriveServiceInterface::class,
            OneDriveService::class
        );

        $this->app->bind(
            MsGraphOutOfOfficeTemplateServiceInterface::class,
            OutOfOfficeTemplateService::class
        );

        $this->app->bind(
            MsGraphGroupServiceInterface::class,
            GroupService::class
        );

        $this->app->bind(
            MsGraphAppServiceInterface::class,
            AppService::class
        );

        $this->app->bind(
            MsGraphIntuneServiceInterface::class,
            IntuneService::class
        );

        $this->app->bind(
            MsGraphShareServiceInterface::class,
            ShareService::class
        );

        $this->app->singleton(TeamsSdkRestClient::class);

        $this->app->bind(
            MsGraphTeamsBotServiceInterface::class,
            TeamsBotService::class
        );

        $this->app->bind(
            MsGraphTeamsActivityFeedServiceInterface::class,
            TeamsActivityFeedService::class
        );
    }
}
