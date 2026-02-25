<?php

namespace Hwkdo\MsGraphLaravel\Interfaces;

interface MsGraphGroupServiceInterface
{
    public function getGroupIdByName(string $name): ?string;

    /**
     * @return array<int, array{id: string, upn: string, displayName: string}>
     */
    public function getGroupMembers(string $groupId): array;

    public function addUserToGroup(string $groupId, string $userId): bool;
}
