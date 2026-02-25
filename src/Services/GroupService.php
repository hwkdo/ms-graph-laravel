<?php

namespace Hwkdo\MsGraphLaravel\Services;

use Exception;
use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphGroupServiceInterface;
use Microsoft\Graph\Generated\Groups\GroupsRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Groups\GroupsRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Models\ReferenceCreate;
use Microsoft\Graph\GraphServiceClient;

class GroupService implements MsGraphGroupServiceInterface
{
    protected static GraphServiceClient $graph;

    public function __construct()
    {
        $g = new Client;
        self::$graph = $g();
    }

    public function getGroupId()
    {
        $displayName = 'GR_HWKDO_GEOLOCATION';

        $filter = "displayName eq '{$displayName}'";
        
        $config = new GroupsRequestBuilderGetRequestConfiguration();
        $config->queryParameters = GroupsRequestBuilderGetRequestConfiguration::createQueryParameters(
            null,    // expand
            null,    // select
            $filter  // filter
        );
        
        $groups = self::$graph->groups()->get($config)->wait();
        
        foreach ($groups->getValue() as $group) {
            echo $group->getId() . ' - ' . $group->getDisplayName() . PHP_EOL;
        }
    }

    public function getGroupIdByName(string $name): ?string
    {
        try {
            // OData-konforme Escaping: Single-Quote wird verdoppelt
            $escapedName = str_replace("'", "''", $name);

            $config = new GroupsRequestBuilderGetRequestConfiguration(
                headers: ['ConsistencyLevel' => 'eventual']
            );
            $config->queryParameters = new GroupsRequestBuilderGetQueryParameters;
            $config->queryParameters->filter = "displayName eq '{$escapedName}'";
            $config->queryParameters->select = ['id', 'displayName'];
            $config->queryParameters->count = true;

            $response = self::$graph->groups()->get($config)->wait();
            $groups = $response->getValue();

            if (empty($groups)) {
                return null;
            }

            return $groups[0]->getId();
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * @return array<int, array{id: string, upn: string, displayName: string}>
     */
    public function getGroupMembers(string $groupId): array
    {
        try {
            $response = self::$graph->groups()
                ->byGroupId($groupId)
                ->members()
                ->get()
                ->wait();

            $members = $response->getValue() ?? [];

            return array_map(function ($member) {
                return [
                    'id' => $member->getId() ?? '',
                    'upn' => method_exists($member, 'getUserPrincipalName')
                        ? ($member->getUserPrincipalName() ?? '')
                        : '',
                    'displayName' => method_exists($member, 'getDisplayName')
                        ? ($member->getDisplayName() ?? $member->getId())
                        : $member->getId(),
                ];
            }, $members);
        } catch (Exception $e) {
            return [];
        }
    }

    public function addUserToGroup(string $groupId, string $userId): bool
    {
        try {
            $ref = new ReferenceCreate;
            $ref->setOdataId('https://graph.microsoft.com/v1.0/directoryObjects/'.$userId);

            self::$graph->groups()
                ->byGroupId($groupId)
                ->members()
                ->ref()
                ->post($ref)
                ->wait();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
