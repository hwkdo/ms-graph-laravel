<?php

namespace Hwkdo\MsGraphLaravel\Interfaces;

use Microsoft\Graph\Generated\Models\FileAttachment;

interface MsGraphMailServiceInterface
{
    public static function get($resource);

    public static function getByUpnAndId($upn, $id);

    public static function listAttachmentsByUpnAndId($upn, $id);

    public static function saveAttachment(FileAttachment $attachment, $path, $filename = null);

    public static function getAttachmentContent(FileAttachment $attachment);

    public static function list($upn, $onlyUnread = false);

    public static function update($upn, $id, $body);

    public static function setRead($upn, $id);
}
