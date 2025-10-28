<?php

namespace Hwkdo\MsGraphLaravel\Database\Seeders;

use Hwkdo\MsGraphLaravel\Jobs\ProcessBestellungenAngebote;
use Hwkdo\MsGraphLaravel\Jobs\ProcessFormwerkMail;
use Hwkdo\MsGraphLaravel\Jobs\ProcessNtopng;
use Hwkdo\MsGraphLaravel\Jobs\ProcessOnedriveFiler;
use Hwkdo\MsGraphLaravel\Jobs\ProcessOnedriveFilerExtern;
use Hwkdo\MsGraphLaravel\Models\GraphWebhookJobMapping;
use Illuminate\Database\Seeder;

class GraphWebhookJobMappingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mappings = [
            [
                'webhook_type' => 'intracollect',
                'name' => 'intracollect_mail',
                'job_class' => ProcessFormwerkMail::class,
                'filepath' => storage_path('app/non-public/files/formwerk/'),
                'upn' => 'intracollect@hwkdoedu.onmicrosoft.com',
                'resource' => "/users/intracollect@hwkdoedu.onmicrosoft.com/mailFolders('inbox')/messages",
                'notification_url' => GraphWebhookJobMapping::generateNotificationUrl('intracollect'),
                'change_type' => 'created',
                'description' => 'Verarbeitet Formwerk Mail Webhooks für IntraCollect',
                'is_active' => true,
            ],
            [
                'webhook_type' => 'angebote',
                'name' => 'angebote_mail',
                'job_class' => ProcessBestellungenAngebote::class,
                'filepath' => storage_path('app/non-public/files/formwerk/'),
                'upn' => 'intrangebote@hwkdoedu.onmicrosoft.com',
                'resource' => "/users/intrangebote@hwkdoedu.onmicrosoft.com/mailFolders('inbox')/messages",
                'notification_url' => GraphWebhookJobMapping::generateNotificationUrl('angebote'),
                'change_type' => 'created',
                'description' => 'Verarbeitet Bestellungen und Angebote Webhooks',
                'is_active' => true,
            ],
            [
                'webhook_type' => 'ntopng',
                'name' => 'ntopng_mail',
                'job_class' => ProcessNtopng::class,
                'filepath' => storage_path('app/non-public/files/formwerk/'),
                'upn' => 'ntopng@hwkdoedu.onmicrosoft.com',
                'resource' => "/users/ntopng@hwkdoedu.onmicrosoft.com/mailFolders('inbox')/messages",
                'notification_url' => GraphWebhookJobMapping::generateNotificationUrl('ntopng'),
                'change_type' => 'created',
                'description' => 'Verarbeitet Ntopng Webhooks',
                'is_active' => true,
            ],
            [
                'webhook_type' => 'onedrive_filer',
                'name' => 'onedrive_filer',
                'job_class' => ProcessOnedriveFiler::class,
                'filepath' => storage_path('app/non-public/files/formwerk/'),
                'upn' => 'filer@hwkdoedu.onmicrosoft.com',
                'resource' => '/users/filer@hwkdoedu.onmicrosoft.com/drive/root',
                'notification_url' => GraphWebhookJobMapping::generateNotificationUrl('onedrive_filer'),
                'change_type' => 'updated',
                'description' => 'Verarbeitet OneDrive Filer Webhooks',
                'is_active' => true,
            ],
            [
                'webhook_type' => 'onedrive_filerextern',
                'name' => 'onedrive_filerextern',
                'job_class' => ProcessOnedriveFilerExtern::class,
                'filepath' => storage_path('app/non-public/files/formwerk/'),
                'upn' => 'filerextern@hwkdoedu.onmicrosoft.com',
                'resource' => '/users/filerextern@hwkdoedu.onmicrosoft.com/drive/root',
                'notification_url' => GraphWebhookJobMapping::generateNotificationUrl('onedrive_filerextern'),
                'change_type' => 'updated',
                'description' => 'Verarbeitet externe OneDrive Filer Webhooks',
                'is_active' => true,
            ],
        ];

        foreach ($mappings as $mapping) {
            GraphWebhookJobMapping::query()->updateOrCreate(
                ['webhook_type' => $mapping['webhook_type']],
                $mapping
            );
        }
    }
}
