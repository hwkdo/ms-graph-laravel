<?php

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;
use Exception;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Graph\Generated\Users\UsersRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Users\UsersRequestBuilderGetQueryParameters;

class UserService implements MsGraphUserServiceInterface
{
    protected static GraphServiceClient $graph;

    public function __construct()
    {
        $g = new Client;
        self::$graph = $g();
    }

    public function getUser($mail)
    {
        try {
            $user = self::getUserByUpn($mail);
        } catch (Exception $e) {
            $user = self::getUserByAlias($mail);
        }

        return get_class($user) == 'Microsoft\Graph\Generated\Models\User'
                ? $user
                : null;
    }

    public function getUserByUpn($upn)
    {
        return self::$graph->users()
            ->byUserId($upn)
            ->get()
            ->wait();
    }

    public function getUserByAlias($alias)
    {
        $filter = "proxyAddresses/any(c:c eq 'SMTP:".$alias."')";

        $requestConfiguration = new \Microsoft\Graph\Generated\Users\UsersRequestBuilderGetRequestConfiguration();
        $requestConfiguration->queryParameters = new \Microsoft\Graph\Generated\Users\UsersRequestBuilderGetQueryParameters();
        $requestConfiguration->queryParameters->filter = $filter;

        $response = self::$graph->users()
            ->get($requestConfiguration)
            ->wait();

        return $response->getValue()[0] ?? null;
    }

    public function update($upn, $data)
    {
        return self::$graph->users()
            ->byUserId($upn)
            ->patch($data)
            ->wait();
    }

    public function getUserPresence($upn)
    {
        return self::$graph->users()
            ->byUserId($upn)
            ->presence()
            ->get()
            ->wait();
    }

    public function getUserTeams($upn)
    {
        $response = self::$graph->users()
            ->byUserId($upn)
            ->joinedTeams()
            ->get()
            ->wait();

        return $response->getValue();
    }

    public function getUserDirectGroups($upn)
    {
        $response = self::$graph->users()
            ->byUserId($upn)
            ->memberOf()
            ->get()
            ->wait();

        return $response->getValue();
    }

    public function getUserTransitiveGroups($upn)
    {
        $response = self::$graph->users()
            ->byUserId($upn)
            ->transitiveMemberOf()
            ->get()
            ->wait();

        return $response->getValue();
    }
}
