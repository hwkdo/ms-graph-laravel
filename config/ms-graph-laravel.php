<?php

return [
    'tenant_id' => env('MSGRAPH_TENTANT_ID'),
    'default_suffix' => env('MSGRAPH_DEFAULT_SUFFIX'),
    'redirect' => env('MICROSOFT_REDIRECT_URI'),

    'azure_app_registrations' => [
        'default' => [
            'client_id' => env('MSGRAPH_APP_ID'),
            'client_secret' => env('MSGRAPH_APP_SECRET_KEY'),
        ],
        'onedrive' => [
            'client_id' => env('MSGRAPH_APP_ID_ONEDRIVE'),
            'client_secret' => env('MSGRAPH_APP_SECRET_KEY_ONEDRIVE'),
        ],
        'subscription' => [
            'client_id' => env('MSGRAPH_APP_ID_SUBSCRIPTION'),
            'client_secret' => env('MSGRAPH_APP_SECRET_KEY_SUBSCRIPTION'),
        ],
    ],

    'subscription_secret' => env('MSGRAPH_SUBSCRIBE_SECRET'),

    'subscriptions' => [
        'intracollect_mail' => [
            'filepath' => storage_path('app/non-public/files/formwerk/'),
            'upn' => 'intracollect@hwkdoedu.onmicrosoft.com',
            'resource' => "/users/intracollect@hwkdoedu.onmicrosoft.com/mailFolders('inbox')/messages",
            'notificationUrl' => 'https://portal.hwkdo.com/api/kunden/IntraCollectMailSubscription/intracollect',
            'changeType' => 'created',
        ],
        'angebote_mail' => [
            'filepath' => storage_path('app/non-public/files/formwerk/'),
            'upn' => 'intrangebote@hwkdoedu.onmicrosoft.com',
            'resource' => "/users/intrangebote@hwkdoedu.onmicrosoft.com/mailFolders('inbox')/messages",
            'notificationUrl' => 'https://portal.hwkdo.com/api/kunden/IntraCollectMailSubscription/angebote',
            'changeType' => 'created',
        ],
        'ntopng_mail' => [
            'filepath' => storage_path('app/non-public/files/formwerk/'),
            'upn' => 'ntopng@hwkdoedu.onmicrosoft.com',
            'resource' => "/users/ntopng@hwkdoedu.onmicrosoft.com/mailFolders('inbox')/messages",
            'notificationUrl' => 'https://portal.hwkdo.com/api/kunden/IntraCollectMailSubscription/ntopng',
            'changeType' => 'created',
        ],
        'onedrive_filer' => [
            'filepath' => storage_path('app/non-public/files/formwerk/'),
            'upn' => 'filer@hwkdoedu.onmicrosoft.com',
            'resource' => '/users/filer@hwkdoedu.onmicrosoft.com/drive/root',
            'notificationUrl' => 'https://portal.hwkdo.com/api/kunden/IntraCollectMailSubscription/onedrive_filer',
            'changeType' => 'updated',
        ],
        'onedrive_filerextern' => [
            'filepath' => storage_path('app/non-public/files/formwerk/'),
            'upn' => 'filerextern@hwkdoedu.onmicrosoft.com',
            'resource' => '/users/filerextern@hwkdoedu.onmicrosoft.com/drive/root',
            'notificationUrl' => 'https://portal.hwkdo.com/api/kunden/IntraCollectMailSubscription/onedrive_filerextern',
            'changeType' => 'updated',
        ],
    ],

    'cache_seconds' => env('MSGRAPH_CACHE_SECONDS', 300),
];
