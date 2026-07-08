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
        'teams_bot' => [
            'client_id' => env('MSGRAPH_TEAMS_BOT_APP_ID'),
            'client_secret' => env('MSGRAPH_TEAMS_BOT_APP_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Teams Bot (Benachrichtigungen per 1:1-Chat)
    |--------------------------------------------------------------------------
    |
    | Azure-Einrichtung (Checkliste):
    | 1. App Registration für Bot (getrennt von MSGRAPH_APP_ID)
    | 2. Azure Bot Resource mit Messaging Endpoint:
    |    https://<teams-sdk-rest-host>/api/messages
    | 3. Laravel Webhook für eingehende Events:
    |    {PORTAL_URL}/api/kunden/ms-graph-laravel/teams-webhook
    | 3. Teams App Manifest + Upload in Organisations-App-Katalog
    |    - bots[].botId = MSGRAPH_TEAMS_BOT_APP_ID
    |    - webApplicationInfo.id = MSGRAPH_TEAMS_BOT_APP_ID (Pflicht für ReadWriteSelfForUser!)
    | 4. Application Permission TeamsAppInstallation.ReadWriteSelfForUser.All + Admin Consent
    |    (oder ReadWriteForUser.All, wenn Manifest-Link nicht möglich)
    |
    | Env: MSGRAPH_TEAMS_BOT_ENABLED, MSGRAPH_TEAMS_BOT_APP_ID,
    |      MSGRAPH_TEAMS_BOT_APP_SECRET, MSGRAPH_TEAMS_APP_CATALOG_ID,
    |      MSGRAPH_TEAMS_BOT_HI_REPLY (optional)
    */
    'teams_bot' => [
        'enabled' => env('MSGRAPH_TEAMS_BOT_ENABLED', false),
        'app_id' => env('MSGRAPH_TEAMS_BOT_APP_ID'),
        'app_secret' => env('MSGRAPH_TEAMS_BOT_APP_SECRET'),
        'teams_app_id' => env('MSGRAPH_TEAMS_APP_CATALOG_ID'),
        'service_url_fallback' => 'https://smba.trafficmanager.net/teams/',
        'hi_reply_message' => env(
            'MSGRAPH_TEAMS_BOT_HI_REPLY',
            'Hallo! Schön, dass du da bist. Ich sende dir Benachrichtigungen aus dem HWKDO Intranet.',
        ),
        'auto_reply_message' => 'Dies ist ein Benachrichtigungs-Bot. Bitte bearbeiten Sie Anfragen im Intranet.',
        'mention_help_message' => env(
            'MSGRAPH_TEAMS_BOT_MENTION_HELP',
            'Du kannst mir z. B. schreiben: „@Bot erstelle mir ein Ticket, dass …", um ein Ticket zu erstellen.',
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | teams-sdk-rest (Node.js Teams SDK Wrapper)
    |--------------------------------------------------------------------------
    |
    | Laravel kommuniziert mit dem Docker-Service external/teams-sdk-rest.
    | Ausgehend: POST /v1/messages (Bearer TEAMS_API_KEY)
    | Eingehend: Webhook mit HMAC X-Teams-Signature
    |
    | Env: TEAMS_SDK_REST_URL, TEAMS_API_KEY, TEAMS_WEBHOOK_SECRET,
    |      TEAMS_SDK_TIMEOUT, TEAMS_WEBHOOK_LOG_REQUESTS (optional)
    */
    'teams_sdk_rest' => [
        'base_url' => env('TEAMS_SDK_REST_URL', 'http://teams-sdk-rest:3978'),
        'api_key' => env('TEAMS_API_KEY'),
        'webhook_secret' => env('TEAMS_WEBHOOK_SECRET'),
        'timeout' => env('TEAMS_SDK_TIMEOUT', 30),
        'log_webhook_requests' => env('TEAMS_WEBHOOK_LOG_REQUESTS', true),
        'log_webhook_payload' => env('TEAMS_WEBHOOK_LOG_PAYLOAD', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Teams Activity Feed (Benachrichtigungen im Activity Feed)
    |--------------------------------------------------------------------------
    |
    | Sendet Benachrichtigungen direkt in den Teams Activity Feed eines Users
    | via Graph API: POST /users/{id}/teamwork/sendActivityNotification
    |
    | Voraussetzungen:
    | 1. Teams-App muss beim Empfänger installiert sein (gleiche App wie Teams Bot)
    | 2. Application Permission TeamsActivity.Send oder TeamsActivity.Send.User
    |    + Admin Consent auf der verwendeten App Registration
    | 3. MSGRAPH_TEAMS_APP_CATALOG_ID muss gesetzt sein (teamsAppId im Request)
    |
    | Env: MSGRAPH_TEAMS_ACTIVITY_FEED_ENABLED, MSGRAPH_TEAMS_ACTIVITY_FEED_TYPE,
    |      MSGRAPH_TEAMS_ACTIVITY_FEED_TOPIC,
    |      MSGRAPH_TEAMS_ACTIVITY_FEED_WEB_URL (optional, muss Teams-Deep-Link sein:
    |      https://teams.microsoft.com/l/…),
    |      MSGRAPH_TEAMS_ACTIVITY_FEED_GRAPH_REGISTRATION (Standard: teams_bot)
    */
    'teams_activity_feed' => [
        'enabled' => env('MSGRAPH_TEAMS_ACTIVITY_FEED_ENABLED', false),
        'activity_type' => env('MSGRAPH_TEAMS_ACTIVITY_FEED_TYPE', 'systemDefault'),
        'topic_title' => env('MSGRAPH_TEAMS_ACTIVITY_FEED_TOPIC', 'HWKDO Intranet'),
        'topic_web_url' => env('MSGRAPH_TEAMS_ACTIVITY_FEED_WEB_URL'),
        'graph_registration' => env('MSGRAPH_TEAMS_ACTIVITY_FEED_GRAPH_REGISTRATION', 'teams_bot'),
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
