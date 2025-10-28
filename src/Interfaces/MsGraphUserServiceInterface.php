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
}
