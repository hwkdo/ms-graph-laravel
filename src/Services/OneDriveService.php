<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use DateTime;
use DateTimeInterface;
use GuzzleHttp\Psr7\Utils;
use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOneDriveServiceInterface;
use Hwkdo\MsGraphLaravel\Support\OnedriveFilename;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\BatchRequestBuilder;
use Microsoft\Graph\Core\Requests\BatchRequestContent;
use Microsoft\Graph\Core\Requests\BatchRequestItem;
use Microsoft\Graph\Generated\Drives\Item\Items\Item\Checkin\CheckinPostRequestBody;
use Microsoft\Graph\Generated\Drives\Item\Items\Item\Children\ChildrenRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Drives\Item\Items\Item\Children\ChildrenRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Drives\Item\Items\Item\CreateLink\CreateLinkPostRequestBody;
use Microsoft\Graph\Generated\Models\Drive;
use Microsoft\Graph\Generated\Models\DriveItem;
use Microsoft\Graph\Generated\Models\DriveItemCollectionResponse;
use Microsoft\Graph\Generated\Models\Folder;
use Microsoft\Graph\Generated\Models\Permission;
use Microsoft\Graph\GraphServiceClient;
use Throwable;

class OneDriveService implements MsGraphOneDriveServiceInterface
{
    protected GraphServiceClient $graph;

    protected ?Drive $cachedDrive = null;

    protected ?string $cachedDriveUpn = null;

    public function __construct(?GraphServiceClient $graph = null)
    {
        $this->graph = $graph ?? (new Client)('onedrive');
    }

    public function getUserDriveDelta($upn, $endpoint = null, $token = null)
    {
        $driveId = $this->driveIdForUser($upn);

        return $this->graph->drives()
            ->byDriveId($driveId)
            ->items()
            ->byDriveItemId('root')
            ->delta()
            ->get()
            ->wait();
    }

    public function getItemIdByPath($upn, $path)
    {
        $item = str($path)->afterLast('/')->value;
        $dir = str($path)->beforeLast('/')->value;
        $dirs = $this->getUserDriveContent($upn, $dir !== '' ? $dir : null);
        $myItem = null;

        foreach ($dirs as $child) {
            if ($child->getName() == $item) {
                $myItem = $child->getId();
            }
        }

        return $myItem;
    }

    /*
     * https://learn.microsoft.com/en-us/graph/api/drive-get?view=graph-rest-1.0&tabs=http
     */
    public function getUserDrive($upn)
    {
        $upn = (string) $upn;

        if ($this->cachedDrive !== null && $this->cachedDriveUpn === $upn) {
            return $this->cachedDrive;
        }

        $drive = $this->retrieveUserDrive($upn);
        $this->cachedDrive = $drive instanceof Drive ? $drive : null;
        $this->cachedDriveUpn = $this->cachedDrive !== null ? $upn : null;

        return $drive;
    }

    public function getUserDriveQuota($upn)
    {
        return $this->getUserDrive($upn)->getQuota();
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/driveitem-list-children?view=graph-rest-1.0
    */
    public function getUserDriveContent($upn, $subdir = null, array $options = [])
    {
        $driveId = $this->driveIdForUser($upn);
        $itemId = $this->itemPathId($subdir);
        $requestConfiguration = $this->childrenGetRequestConfiguration($options);

        $response = $this->graph->drives()
            ->byDriveId($driveId)
            ->items()
            ->byDriveItemId($itemId)
            ->children()
            ->get($requestConfiguration)
            ->wait();

        return $response?->getValue() ?? [];
    }

    /**
     * @param  list<string>  $subdirs
     * @return array<string, list<DriveItem>>
     */
    public function batchGetUserDriveContents(string $upn, array $subdirs): array
    {
        $subdirs = array_values(array_unique(array_map(
            fn (mixed $subdir): string => is_string($subdir) ? trim($subdir, '/') : '',
            $subdirs,
        )));

        if ($subdirs === []) {
            return [];
        }

        $driveId = $this->driveIdForUser($upn);
        $result = [];

        foreach (array_chunk($subdirs, 20) as $chunk) {
            $batchItems = [];
            $pathByRequestId = [];

            foreach ($chunk as $subdir) {
                $itemId = $this->itemPathId($subdir !== '' ? $subdir : null);
                $requestInfo = $this->graph->drives()
                    ->byDriveId($driveId)
                    ->items()
                    ->byDriveItemId($itemId)
                    ->children()
                    ->toGetRequestInformation();

                $batchItem = new BatchRequestItem($requestInfo);
                $batchItems[] = $batchItem;
            }

            $batchContent = new BatchRequestContent($batchItems);
            $requests = $batchContent->getRequests();

            foreach ($requests as $index => $request) {
                $pathByRequestId[$request->getId()] = $chunk[$index] ?? '';
            }

            $batchBuilder = new BatchRequestBuilder($this->graph->getRequestAdapter());
            $batchResponse = $batchBuilder->postAsync($batchContent)->wait();

            foreach ($pathByRequestId as $requestId => $subdir) {
                try {
                    $responseItem = $batchResponse->getResponse($requestId);
                } catch (Throwable) {
                    $result[$subdir] = [];

                    continue;
                }

                if ($responseItem === null || $responseItem->getStatusCode() !== 200) {
                    $result[$subdir] = [];

                    continue;
                }

                $body = $batchResponse->getResponseBody($requestId, DriveItemCollectionResponse::class);
                $result[$subdir] = $body?->getValue() ?? [];
            }
        }

        return $result;
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/drive-list?view=graph-rest-1.0
    */
    public function getUserDrives($upn)
    {
        $response = $this->graph->users()
            ->byUserId($upn)
            ->drives()
            ->get()
            ->wait();

        return $response?->getValue() ?? [];
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/driveitem-delete?view=graph-rest-1.0
    */
    public function deleteItemById($drive_id, $item_id)
    {
        return $this->graph->drives()
            ->byDriveId($drive_id)
            ->items()
            ->byDriveItemId($item_id)
            ->delete()
            ->wait();
    }

    public function deleteItemByPath($upn, $path)
    {
        $itemId = $this->getItemIdByPath($upn, $path);
        $driveId = $this->getUserDrive(config('intranet.ms_graph.upn_file_service_account'))->getId();

        return $this->deleteItemById($driveId, $itemId);
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/driveitem-put-content?view=graph-rest-1.0
     * Upload bis ~250 MB
    */
    public function uploadItemToUserDrive($upn, $filename, $path_to_file, $subdir = null)
    {
        $filename = OnedriveFilename::sanitize($filename);
        $driveId = $this->driveIdForUser($upn);

        $relativePath = $subdir
            ? trim((string) $subdir, '/').'/'.$filename
            : $filename;

        $stream = Utils::streamFor(fopen($path_to_file, 'r'));

        return $this->graph->drives()
            ->byDriveId($driveId)
            ->items()
            ->byDriveItemId($this->itemPathId($relativePath))
            ->content()
            ->put($stream)
            ->wait();
    }

    /*
     * https://learn.microsoft.com/en-us/graph/api/permission-update?view=graph-rest-1.0
     */
    public function updateLink($upn, $item_id, $perm_id, $data)
    {
        $driveId = $this->driveIdForUser($upn);
        $permission = new Permission;

        if (is_array($data)) {
            $permission->setAdditionalData($data);
        } elseif ($data instanceof Permission) {
            $permission = $data;
        }

        return $this->graph->drives()
            ->byDriveId($driveId)
            ->items()
            ->byDriveItemId($item_id)
            ->permissions()
            ->byPermissionId($perm_id)
            ->patch($permission)
            ->wait();
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/driveitem-createlink?view=graph-rest-1.0
    */
    public function createLink($upn, $item_id, $type, $scope, $password = null, $expirationDateTime = null)
    {
        $driveId = $this->driveIdForUser($upn);

        $body = new CreateLinkPostRequestBody;
        $body->setType($type);
        $body->setScope($scope);
        $body->setRetainInheritedPermissions(false);

        if ($password) {
            $body->setPassword($password);
        }

        if ($expirationDateTime) {
            $parsed = $expirationDateTime instanceof DateTimeInterface
                ? DateTime::createFromInterface($expirationDateTime)
                : new DateTime((string) $expirationDateTime);
            $body->setExpirationDateTime($parsed);
        }

        return $this->graph->drives()
            ->byDriveId($driveId)
            ->items()
            ->byDriveItemId($item_id)
            ->createLink()
            ->post($body)
            ->wait();
    }

    public function shareReadOnly($upn, $item_id, $password = null, $expirationDateTime = null)
    {
        $result = $this->createLink($upn, $item_id, 'view', 'anonymous', $password, $expirationDateTime);

        return $result->getLink()->getWebUrl();
    }

    public function shareReadWrite($upn, $item_id, $password = null, $expirationDateTime = null)
    {
        $result = $this->createLink($upn, $item_id, 'edit', 'anonymous', $password, $expirationDateTime);

        return $result->getLink()->getWebUrl();
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/driveitem-post-children?view=graph-rest-1.0
    */
    public function newDir($upn, $dir_name, $subdir = null)
    {
        $driveId = $this->driveIdForUser($upn);
        $parentId = $this->itemPathId($subdir);

        $driveItem = new DriveItem;
        $driveItem->setName($dir_name);
        $driveItem->setFolder(new Folder);
        $driveItem->setAdditionalData([
            '@microsoft.graph.conflictBehavior' => 'rename',
        ]);

        return $this->graph->drives()
            ->byDriveId($driveId)
            ->items()
            ->byDriveItemId($parentId)
            ->children()
            ->post($driveItem)
            ->wait();
    }

    public function makeFolder($upn, $folder)
    {
        $subdirs = explode('/', $folder);
        Log::debug('------- makeFolder2', $subdirs);
        $rootItems = $this->getUserDriveContent($upn);
        $rootItem = false;

        foreach ($rootItems as $item) {
            if ($item->getName() == $subdirs[0] && $item->getFolder()) {
                $rootItem = $item;
                Log::debug('rootItem found', [$rootItem]);
            }
        }

        if (! $rootItem) {
            $rootItem = $this->newDir($upn, $subdirs[0]);
            Log::debug('rootItem created', [$rootItem]);
        }

        if (count($subdirs) == 1) {
            return $rootItem;
        }

        if (count($subdirs) > 1) {
            $dir = $subdirs[0];
            Log::debug('pre-loop setze dir auf '.$dir);

            for ($i = 1; $i < count($subdirs); $i++) {
                Log::debug($i.' < '.count($subdirs));
                Log::debug('not last round');

                $subItem = false;
                $subItems = $this->getUserDriveContent($upn, $dir);
                Log::info('Suche SubItem  '.$subdirs[$i].' in dir '.$dir, is_array($subItems) ? $subItems : [$subItems]);

                foreach ($subItems as $item) {
                    if ($item->getName() == $subdirs[$i] && $item->getFolder()) {
                        $subItem = $item;
                        Log::debug('subItem found', [$subItem]);
                    }
                }

                if (! $subItem) {
                    $subItem = $this->newDir($upn, $subdirs[$i], $dir);
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
    ** https://learn.microsoft.com/de-de/graph/api/driveitem-checkin?view=graph-rest-1.0
    */
    public function checkIn($upn, $item_id)
    {
        $driveId = $this->driveIdForUser($upn);

        return $this->graph->drives()
            ->byDriveId($driveId)
            ->items()
            ->byDriveItemId($item_id)
            ->checkin()
            ->post(new CheckinPostRequestBody)
            ->wait();
    }

    /*
    ** https://learn.microsoft.com/de-de/graph/api/driveitem-checkout?view=graph-rest-1.0
    */
    public function checkOut($upn, $item_id)
    {
        $driveId = $this->driveIdForUser($upn);

        return $this->graph->drives()
            ->byDriveId($driveId)
            ->items()
            ->byDriveItemId($item_id)
            ->checkout()
            ->post()
            ->wait();
    }

    public function getDriveItemPermissions($upn, $item_id, $scope = null)
    {
        $driveId = $this->driveIdForUser($upn);

        $response = $this->graph->drives()
            ->byDriveId($driveId)
            ->items()
            ->byDriveItemId($item_id)
            ->permissions()
            ->get()
            ->wait();

        $data = $response?->getValue() ?? [];

        if (! $scope) {
            return $data;
        }

        return collect($data)->filter(function ($perm) use ($scope) {
            return $perm->getLink() && $perm->getLink()->getScope() == $scope;
        });
    }

    /**
     * Graph-Abruf ohne Instance-Cache — überschreibbar in Tests.
     */
    protected function retrieveUserDrive(string $upn): mixed
    {
        return $this->graph->users()
            ->byUserId($upn)
            ->drive()
            ->get()
            ->wait();
    }

    protected function driveIdForUser(string $upn): string
    {
        $drive = $this->getUserDrive($upn);
        $driveId = $drive?->getId();

        if (! is_string($driveId) || $driveId === '') {
            throw new \RuntimeException('OneDrive-ID für Benutzer '.$upn.' konnte nicht ermittelt werden.');
        }

        return $driveId;
    }

    /**
     * Path-basierte DriveItem-ID für Graph SDK v2 (kein itemWithPath).
     * root bzw. root:/relativer/pfad:
     */
    protected function itemPathId(?string $subdir = null): string
    {
        $subdir = is_string($subdir) ? trim($subdir, '/') : '';

        if ($subdir === '') {
            return 'root';
        }

        return 'root:/'.$subdir.':';
    }

    /**
     * @param  array{expand?: list<string>}  $options
     */
    protected function childrenGetRequestConfiguration(array $options = []): ?ChildrenRequestBuilderGetRequestConfiguration
    {
        $expand = $options['expand'] ?? null;

        if (! is_array($expand) || $expand === []) {
            return null;
        }

        $config = new ChildrenRequestBuilderGetRequestConfiguration;
        $config->queryParameters = new ChildrenRequestBuilderGetQueryParameters;
        $config->queryParameters->expand = array_values(array_filter(
            $expand,
            fn (mixed $value): bool => is_string($value) && $value !== '',
        ));

        return $config;
    }
}
