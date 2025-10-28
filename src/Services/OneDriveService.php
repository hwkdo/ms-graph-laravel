<?php

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOneDriveServiceInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Microsoft\Graph\Generated\Models\DriveItem;
use Microsoft\Graph\GraphServiceClient;

class OneDriveService implements MsGraphOneDriveServiceInterface
{
    protected static GraphServiceClient $graph;

    public function __construct()
    {
        $g = new Client;
        self::$graph = $g('onedrive');
    }

    public function getUserDriveDelta($upn, $endpoint = null, $token = null)
    {
        $myEndpoint = $endpoint ? $endpoint : '/users/'.$upn.'/drive/root/delta';

        // Note: Delta queries may need special handling in v2
        return self::$graph->users()
            ->byUserId($upn)
            ->drive()
            ->root()
            ->delta()
            ->get()
            ->wait();
    }

    public function getItemIdByPath($upn, $path)
    {
        $item = str($path)->afterLast('/')->value;
        $dir = str($path)->beforeLast('/')->value;
        $dirs = self::getUserDriveContent($upn, $dir);
        $myItem = null;
        foreach ($dirs as $dir) {
            if ($dir->getName() == $item) {
                $myItem = $dir->getId();
            }
        }

        return $myItem;
    }

    /*
     * https://learn.microsoft.com/en-us/graph/api/drive-get?view=graph-rest-1.0&tabs=http
     */
    public function getUserDrive($upn)
    {
        return self::$graph->users()
            ->byUserId($upn)
            ->drive()
            ->get()
            ->wait();
    }

    public function getUserDriveQuota($upn)
    {
        return self::getUserDrive($upn)->getQuota();
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/drive-get?view=graph-rest-beta&tabs=http#get-a-users-onedrive
    */
    public function getUserDriveContent($upn, $subdir = null)
    {
        if ($subdir) {
            $endpoint = '/users/'.$upn.'/drive/root:/'.$subdir.':/children';

            // Note: This may need special handling for path-based access in v2
            $response = self::$graph->users()
                ->byUserId($upn)
                ->drive()
                ->root()
                ->itemWithPath($subdir)
                ->children()
                ->get()
                ->wait();

            return $response->getValue();
        } else {
            $response = self::$graph->users()
                ->byUserId($upn)
                ->drive()
                ->root()
                ->children()
                ->get()
                ->wait();

            return $response->getValue();
        }
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/drive-get?view=graph-rest-beta&tabs=http#get-a-users-onedrive
    */
    public function getUserDrives($upn)
    {
        $response = self::$graph->users()
            ->byUserId($upn)
            ->drives()
            ->get()
            ->wait();

        return $response->getValue();
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/driveitem-delete?view=graph-rest-beta&tabs=http
    */
    public function deleteItemById($drive_id, $item_id)
    {
        return self::$graph->drives()
            ->byDriveId($drive_id)
            ->items()
            ->byDriveItemId($item_id)
            ->delete()
            ->wait();
    }

    public function deleteItemByPath($upn, $path)
    {
        $itemId = self::getItemIdByPath($upn, $path);
        $driveId = self::getUserDrive(config('intranet.ms_graph.upn_file_service_account'))->getId();

        return self::deleteItemById($driveId, $itemId);
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/driveitem-put-content?view=graph-rest-beta
     * UPLOAD biIS 250 MB
     * fuer groeßere uploads siehe hier https://learn.microsoft.com/de-de/graph/api/driveitem-createuploadsession?view=graph-rest-beta
     *
    */
    public function uploadItemToUserDrive($upn, $filename, $path_to_file, $subdir = null)
    {
        $filename = Str::slug(Str::ascii($filename)).'.'.Str::afterLast($filename, '.');

        if ($subdir) {
            return self::$graph->users()
                ->byUserId($upn)
                ->drive()
                ->root()
                ->itemWithPath($subdir)
                ->itemWithPath($filename)
                ->content()
                ->put($path_to_file)
                ->wait();
        } else {
            return self::$graph->users()
                ->byUserId($upn)
                ->drive()
                ->root()
                ->itemWithPath($filename)
                ->content()
                ->put($path_to_file)
                ->wait();
        }
    }

    /*
     * https://learn.microsoft.com/en-us/graph/api/permission-update?view=graph-rest-beta&tabs=http
     */
    public function updateLink($upn, $item_id, $perm_id, $data)
    {
        return self::$graph->users()
            ->byUserId($upn)
            ->drive()
            ->items()
            ->byDriveItemId($item_id)
            ->permissions()
            ->byPermissionId($perm_id)
            ->patch($data)
            ->wait();
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/driveitem-createlink?view=graph-rest-beta&tabs=http#link-types
     * types:
     *  - view (read only)
     *  - blocksDownload (read-only und download sperre)
     *  - edit
     *  - createOnly (nur upload)
     * scopes:
     *  - anonymous
     *  - organization
     *  - users
    */
    public function createLink($upn, $item_id, $type, $scope, $password = null, $expirationDateTime = null)
    {
        $data = [
            'type' => $type,
            'scope' => $scope,
            'retainInheritedPermissions' => false,
        ];
        if ($password) {
            $data['password'] = $password;
        }
        if ($expirationDateTime) {
            $data['expirationDateTime'] = \Carbon\Carbon::parse($expirationDateTime)->toIso8601ZuluString();
        }

        return self::$graph->users()
            ->byUserId($upn)
            ->drive()
            ->items()
            ->byDriveItemId($item_id)
            ->createLink()
            ->post($data)
            ->wait();
    }

    public function shareReadOnly($upn, $item_id, $password = null, $expirationDateTime = null)
    {
        $result = self::createLink($upn, $item_id, 'view', 'anonymous', $password, $expirationDateTime);

        return $result->getLink()->getWebUrl();
    }

    public function shareReadWrite($upn, $item_id, $password = null, $expirationDateTime = null)
    {
        $result = self::createLink($upn, $item_id, 'edit', 'anonymous', $password, $expirationDateTime);

        return $result->getLink()->getWebUrl();
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/driveitem-post-children?view=graph-rest-beta&tabs=http
    */
    public function newDir($upn, $dir_name, $subdir = null)
    {
        $data = [
            'name' => $dir_name,
            'folder' => [],
            '@microsoft.graph.conflictBehavior' => 'rename',
        ];

        if ($subdir) {
            return self::$graph->users()
                ->byUserId($upn)
                ->drive()
                ->root()
                ->itemWithPath($subdir)
                ->children()
                ->post($data)
                ->wait();
        } else {
            return self::$graph->users()
                ->byUserId($upn)
                ->drive()
                ->root()
                ->children()
                ->post($data)
                ->wait();
        }
    }

    public function makeFolder($upn, $folder)
    {
        $subdirs = explode('/', $folder);
        Log::debug('------- makeFolder2', $subdirs);
        $rootItems = self::getUserDriveContent($upn);
        $rootItem = false;
        foreach ($rootItems as $item) {
            if ($item->getName() == $subdirs[0] && $item->getFolder()) {
                $rootItem = $item;
                Log::debug('rootItem found', [$rootItem]);
            }
        }
        if (! $rootItem) {
            $rootItem = self::newDir($upn, $subdirs[0]);
            Log::debug('rootItem created', [$rootItem]);
        }

        if (count($subdirs) == 1) {
            return $rootItem;
        } elseif (count($subdirs) > 1) {
            $dir = $subdirs[0];
            Log::debug('pre-loop setze dir auf '.$dir);
            for ($i = 1; $i < count($subdirs); $i++) {
                Log::debug($i.' < '.count($subdirs));
                Log::debug('not last round');

                $subItem = false;
                $subItems = self::getUserDriveContent($upn, $dir);
                Log::info('Suche SubItem  '.$subdirs[$i].' in dir '.$dir, $subItems);
                foreach ($subItems as $item) {
                    if ($item->getName() == $subdirs[$i] && $item->getFolder()) {
                        $subItem = $item;
                        Log::debug('subItem found', [$subItem]);
                    }
                }
                if (! $subItem) {
                    $subItem = self::newDir($upn, $subdirs[$i], $dir);
                    Log::debug('subItem created in '.$dir, [$subItem]);
                }
                Log::info('SubItem Name '.$subItem->getName(), [$subItem]);
                $dir .= '/'.$subdirs[$i];
                Log::info('in-loop setze dir auf '.$dir);
            }

            return $subItem;
        }
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/driveitem-checkin?view=graph-rest-1.0&tabs=http
    */
    public function checkIn($upn, $item_id)
    {
        return self::$graph->users()
            ->byUserId($upn)
            ->drive()
            ->items()
            ->byDriveItemId($item_id)
            ->checkin()
            ->post()
            ->wait();
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/driveitem-checkout?view=graph-rest-1.0&tabs=http
    */
    public function checkOut($upn, $item_id)
    {
        return self::$graph->users()
            ->byUserId($upn)
            ->drive()
            ->items()
            ->byDriveItemId($item_id)
            ->checkout()
            ->post()
            ->wait();
    }

    public function getDriveItemPermissions($upn, $item_id, $scope = null)
    {
        $response = self::$graph->users()
            ->byUserId($upn)
            ->drive()
            ->items()
            ->byDriveItemId($item_id)
            ->permissions()
            ->get()
            ->wait();

        $data = $response->getValue();

        if (! $scope) {
            return $data;
        }

        return collect($data)->filter(function ($perm) use ($scope) {
            return $perm->getLink() && $perm->getLink()->getScope() == $scope;
        });
    }
}
