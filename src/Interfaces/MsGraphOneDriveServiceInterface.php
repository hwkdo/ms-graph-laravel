<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Interfaces;

interface MsGraphOneDriveServiceInterface
{
    public function getUserDrive($upn);

    public function getUserDriveQuota($upn);

    /**
     * @param  array{expand?: list<string>}  $options
     */
    public function getUserDriveContent($upn, $subdir = null, array $options = []);

    /**
     * @param  list<string>  $subdirs
     * @return array<string, list<object>>
     */
    public function batchGetUserDriveContents(string $upn, array $subdirs): array;

    public function getUserDrives($upn);

    public function deleteItemById($drive_id, $item_id);

    public function deleteItemByPath($upn, $path);

    public function uploadItemToUserDrive($upn, $filename, $path_to_file, $subdir = null);

    public function updateLink($upn, $item_id, $perm_id, $data);

    public function createLink($upn, $item_id, $type, $scope, $password = null, $expirationDateTime = null);

    public function shareReadOnly($upn, $item_id, $password = null, $expirationDateTime = null);

    public function shareReadWrite($upn, $item_id, $password = null, $expirationDateTime = null);

    public function newDir($upn, $dir_name, $subdir = null);

    public function makeFolder($upn, $folder);

    public function getDriveItemPermissions($upn, $item_id, $scope = null);
}
