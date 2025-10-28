<?php

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Microsoft\Graph\Generated\Models\FileAttachment;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Graph\Generated\Users\Item\Messages\MessagesRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Users\Item\Messages\MessagesRequestBuilderGetQueryParameters;

class MailService
{
    protected static GraphServiceClient $graph;

    public function __construct()
    {
        $g = new Client;
        self::$graph = $g();
    }

    public static function get($resource)
    {
        return self::$graph->me()
            ->messages()
            ->byMessageId($resource)
            ->get()
            ->wait();
    }

    public static function getByUpnAndId($upn, $id)
    {
        return self::$graph->users()
            ->byUserId($upn)
            ->messages()
            ->byMessageId($id)
            ->get()
            ->wait();
    }

    public static function listAttachmentsByUpnAndId($upn, $id)
    {
        return self::$graph->users()
            ->byUserId($upn)
            ->messages()
            ->byMessageId($id)
            ->attachments()
            ->get()
            ->wait();
    }

    public static function saveAttachment(FileAttachment $attachment, $path, $filename = null)
    {
        if (! $filename) {
            $filename = $attachment->getName();
            Log::info('MsGraph - MailService - saveAttachment | No Filename given, using '.$filename);
        }
        if (! File::exists($path)) {
            Log::info('MsGraph - MailService - saveAttachment | Path '.$path.' does not exist. Creating');
            File::makeDirectory($path);
        }
        $put = File::put($path.$filename, self::getAttachmentContent($attachment));
        if ($put) {
            Log::info('MsGraph - MailService - saveAttachment | '.$path.$filename.' saved');
        }

        return $path.$filename;
    }

    public static function getAttachmentContent(FileAttachment $attachment)
    {
        return base64_decode(
            $attachment->getContentBytes()->getContents()
        );
    }

    public static function list($upn, $onlyUnread = false)
    {
        $requestConfiguration = new \Microsoft\Graph\Generated\Users\Item\Messages\MessagesRequestBuilderGetRequestConfiguration();
        $requestConfiguration->queryParameters = new \Microsoft\Graph\Generated\Users\Item\Messages\MessagesRequestBuilderGetQueryParameters();
        
        if ($onlyUnread) {
            $requestConfiguration->queryParameters->filter = 'isRead eq false';
        }

        return self::$graph->users()
            ->byUserId($upn)
            ->messages()
            ->get($requestConfiguration)
            ->wait();
    }

    public static function update($upn, $id, $body)
    {
        return self::$graph->users()
            ->byUserId($upn)
            ->messages()
            ->byMessageId($id)
            ->patch($body)
            ->wait();
    }

    public static function setRead($upn, $id)
    {
        $body = [];
        $body['isRead'] = true;

        return self::update($upn, $id, $body);
    }
}
