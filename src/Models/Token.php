<?php

namespace Hwkdo\MsGraphLaravel\Models;

use Illuminate\Database\Eloquent\Model;

class Token extends Model
{
    protected $guarded = [];

    protected $table = 'ms_graph_laravel_tokens';

    protected $casts = [
        'expiration' => 'datetime',
    ];

    public static function getToken($type): string
    {
        $newest = self::where('type', $type)->orderBy('expiration')->first();
        if ($newest && $newest->expiration > \Carbon\Carbon::now()) {
            return $newest->token;
        } elseif (! $newest) {
            $token = self::newToken($type);

            return $token;
        }
        $newest->delete();
        $token = self::newToken($type);

        return $token;
    }

    private static function newToken($type): string
    {
        $guzzle = new \GuzzleHttp\Client;
        $url = 'https://login.microsoftonline.com/'.config('ms-graph-laravel.tenant_id').'/oauth2/v2.0/token';
        $accessToken = json_decode($guzzle->post($url, [
            'form_params' => [
                'client_id' => config('ms-graph-laravel.azure_app_registrations.'.$type.'.client_id'),
                'client_secret' => config('ms-graph-laravel.azure_app_registrations.'.$type.'.client_secret'),
                'scope' => 'https://graph.microsoft.com/.default',
                'grant_type' => 'client_credentials',
            ],
        ])->getBody()->getContents());
        $token = self::create([
            'token' => $accessToken->access_token,
            'expiration' => \Carbon\Carbon::now()->addSeconds($accessToken->expires_in),
            'type' => $type,
        ]);

        return $token->token;
    }
}
