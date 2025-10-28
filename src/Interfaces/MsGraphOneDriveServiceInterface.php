<?php

namespace Hwkdo\MsGraphLaravel\Interfaces;

interface MsGraphOneDriveServiceInterface
{
    public function getUserDrive($upn);

    public function getUserDriveQuota($upn);

    public function getUserDriveContent($upn, $subdir = null);

    public function getUserDrives($upn);

    public function deleteItemById($drive_id, $item_id);

    public function deleteItemByPath($upn, $path);

    public function uploadItemToUserDrive($upn, $filename, $path_to_file, $subdir = null);

    public function updateLink($upn, $item_id, $perm_id, $data);

    public function createLink($upn, $item_id, $type, $scope, $password = null, $expirationDateTime = null);

    public function shareReadOnly($upn, $item_id, $password = null, $expirationDateTime = null);

    public function shareReadWrite($upn, $item_id, $password = null, $expirationDateTime = null);

    public function newDir($upn, $dir_name, $subdir = null);
}
