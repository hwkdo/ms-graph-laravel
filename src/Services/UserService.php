<?php

namespace Hwkdo\MsGraphLaravel\Services;

use Exception;
use Hwkdo\MsGraphLaravel\Client;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphUserServiceInterface;
use Hwkdo\MsGraphLaravel\Models\Token;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;
use Microsoft\Graph\Generated\Models\User;
use Microsoft\Graph\Generated\Users\Item\UserItemRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Users\Item\UserItemRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Users\UsersRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Users\UsersRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Abstractions\ApiException;

class UserService implements MsGraphUserServiceInterface
{
    protected static GraphServiceClient $graph;

    public function __construct()
    {
        $g = new Client;
        self::$graph = $g();
    }

    public function getAllUsers()
    {
        $response = self::$graph->users()
            ->get()
            ->wait();

        return $response->getValue();
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

        $requestConfiguration = new UsersRequestBuilderGetRequestConfiguration;
        $requestConfiguration->queryParameters = new UsersRequestBuilderGetQueryParameters;
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

    public function getUsersPaginated(int $top, ?string $search = null, ?string $nextLink = null): array
    {
        // Wenn ein nextLink vorhanden ist, verwenden wir diesen
        if ($nextLink) {
            // Hole den Access Token
            $token = Token::getToken('default');

            // Mache eine direkte HTTP-Anfrage an die nextLink URL
            $client = new \GuzzleHttp\Client;
            $httpResponse = $client->get($nextLink, [
                'headers' => [
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($httpResponse->getBody()->getContents(), true);

            // Konvertiere die Rohdaten zurück in User-Objekte
            $users = array_map(function ($userData) {
                $user = new User;
                $user->setId($userData['id'] ?? null);
                $user->setUserPrincipalName($userData['userPrincipalName'] ?? null);
                $user->setDisplayName($userData['displayName'] ?? null);
                $user->setMail($userData['mail'] ?? null);
                $user->setJobTitle($userData['jobTitle'] ?? null);

                return $user;
            }, $data['value'] ?? []);

            return [
                'users' => $users,
                'nextLink' => $data['@odata.nextLink'] ?? null,
            ];
        }

        // Normale erste Seite
        $requestConfiguration = new UsersRequestBuilderGetRequestConfiguration;
        $requestConfiguration->queryParameters = new UsersRequestBuilderGetQueryParameters;
        $requestConfiguration->queryParameters->top = $top;

        // Wenn eine Suche vorhanden ist, erstellen wir einen Filter
        if ($search) {
            $searchTerm = str_replace("'", "''", $search); // Escape single quotes
            $filter = sprintf(
                "startsWith(userPrincipalName, '%s') or startsWith(displayName, '%s') or startsWith(givenName, '%s') or startsWith(surname, '%s')",
                $searchTerm,
                $searchTerm,
                $searchTerm,
                $searchTerm
            );
            $requestConfiguration->queryParameters->filter = $filter;
        }

        $response = self::$graph->users()
            ->get($requestConfiguration)
            ->wait();

        $users = $response->getValue() ?? [];
        $responseNextLink = $response->getOdataNextLink();

        return [
            'users' => $users,
            'nextLink' => $responseNextLink,
        ];
    }

    public function getUserDetails(string $upn): ?object
    {
        try {
            $requestConfiguration = new UserItemRequestBuilderGetRequestConfiguration;
            $requestConfiguration->queryParameters = new UserItemRequestBuilderGetQueryParameters;
            $requestConfiguration->queryParameters->select = [
                'id',
                'userPrincipalName',
                'displayName',
                'mail',
                'jobTitle',
                'department',
                'mobilePhone',
                'officeLocation',
                'businessPhones',
                'accountEnabled',
            ];

            $user = self::$graph->users()
                ->byUserId($upn)
                ->get($requestConfiguration)
                ->wait();

            // Zusätzliche Details laden
            $details = [
                'user' => $user,
                'groups' => null,
                'teams' => null,
                'manager' => null,
            ];

            // Groups laden
            try {
                $groupsResponse = self::$graph->users()
                    ->byUserId($upn)
                    ->memberOf()
                    ->get()
                    ->wait();
                $details['groups'] = $groupsResponse->getValue();
            } catch (Exception $e) {
                // Ignoriere Fehler beim Laden der Groups
            }

            // Teams laden
            try {
                $teamsResponse = self::$graph->users()
                    ->byUserId($upn)
                    ->joinedTeams()
                    ->get()
                    ->wait();
                $details['teams'] = $teamsResponse->getValue();
            } catch (Exception $e) {
                // Ignoriere Fehler beim Laden der Teams
            }

            // Manager laden
            try {
                $details['manager'] = self::$graph->users()
                    ->byUserId($upn)
                    ->manager()
                    ->get()
                    ->wait();
            } catch (Exception $e) {
                // Ignoriere Fehler beim Laden des Managers
            }

            return (object) $details;
        } catch (Exception $e) {
            return null;
        }
    }

    public function activateUser(string $upn): bool
    {
        try {
            $user = new User;
            $user->setAccountEnabled(true);

            self::$graph->users()
                ->byUserId($upn)
                ->patch($user)
                ->wait();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function removeUserFromGroup(string $upn, string $groupId): bool
    {
        try {
            $user = self::getUserByUpn($upn);
            $userId = $user->getId();
        } catch (Exception $e) {
            Log::warning('MsGraph UserService: removeUserFromGroup failed (user lookup)', [
                'upn' => $upn,
                'group_id' => $groupId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'status_code' => $e instanceof ApiException ? $e->getResponseStatusCode() : null,
                'error_code' => $e instanceof ODataError ? $e->getError()?->getCode() : null,
            ]);

            return false;
        }

        try {
            self::$graph->groups()
                ->byGroupId($groupId)
                ->members()
                ->byDirectoryObjectId($userId)
                ->ref()
                ->delete()
                ->wait();

            return true;
        } catch (Exception $e) {
            if ($this->isAlreadyNotAGroupMember($e)) {
                Log::info('MsGraph UserService: removeUserFromGroup — User bereits nicht in Gruppe', [
                    'upn' => $upn,
                    'group_id' => $groupId,
                ]);

                return true;
            }

            Log::warning('MsGraph UserService: removeUserFromGroup failed', [
                'upn' => $upn,
                'group_id' => $groupId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'status_code' => $e instanceof ApiException ? $e->getResponseStatusCode() : null,
                'error_code' => $e instanceof ODataError ? $e->getError()?->getCode() : null,
            ]);

            return false;
        }
    }

    private function isAlreadyNotAGroupMember(Exception $e): bool
    {
        if ($e instanceof ApiException && $e->getResponseStatusCode() === 404) {
            return true;
        }

        if (! $e instanceof ODataError) {
            return false;
        }

        $code = $e->getError()?->getCode();

        return in_array($code, ['Request_ResourceNotFound', 'ResourceNotFound'], true);
    }
}
