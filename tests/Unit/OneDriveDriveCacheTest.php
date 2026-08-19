<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Services\OneDriveService;
use Microsoft\Graph\Generated\Models\Drive;

it('holt getUserDrive nur einmal pro upn innerhalb einer instanz', function (): void {
    $service = new class extends OneDriveService
    {
        public int $remoteCalls = 0;

        public function __construct()
        {
            // Graph-Client nicht initialisieren — retrieveUserDrive ist überschrieben.
        }

        protected function retrieveUserDrive(string $upn): mixed
        {
            $this->remoteCalls++;

            $drive = new Drive;
            $drive->setId('drive-'.$upn);

            return $drive;
        }
    };

    expect($service->getUserDrive('user@example.com')->getId())->toBe('drive-user@example.com')
        ->and($service->getUserDrive('user@example.com')->getId())->toBe('drive-user@example.com')
        ->and($service->remoteCalls)->toBe(1);

    expect($service->getUserDrive('other@example.com')->getId())->toBe('drive-other@example.com')
        ->and($service->remoteCalls)->toBe(2);
});
