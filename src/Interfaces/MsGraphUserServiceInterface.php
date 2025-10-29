<?php

namespace Hwkdo\MsGraphLaravel\Interfaces;

interface MsGraphUserServiceInterface
{
    public function getUserPresence($upn);

    public function getUserTeams($upn);

    public function getUser($mail);

    public function getUserByUpn($upn);

    public function getUserByAlias($alias);

    public function update($upn, $data);

    public function getUserDirectGroups($upn);

    public function getUserTransitiveGroups($upn);

    public function getAllUsers();

    public function getUsersPaginated(int $top, ?string $search = null, ?string $nextLink = null): array;

    public function getUserDetails(string $upn): ?object;

    public function activateUser(string $upn): bool;

    public function removeUserFromGroup(string $upn, string $groupId): bool;
}
