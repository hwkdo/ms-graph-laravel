<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Interfaces;

use Illuminate\Contracts\Auth\Authenticatable;

interface MsGraphOneNoteServiceInterface
{
    /**
     * @return list<array{id: string, name: string, sectionsUrl: string, sectionGroupsUrl: string, webUrl: string, ownerUserId: string}>
     */
    public function listNotebooks(Authenticatable $actor, string $ownerType, string $ownerId): array;

    /**
     * @return array{requestUrl: string, sections: list<array{id: string, name: string, pagesUrl: string}>}
     */
    public function listSections(Authenticatable $actor, string $sectionsUrl, string $sectionGroupsUrl, string $webUrl = ''): array;

    /**
     * @return array{requestUrl: string, pages: list<array{id: string, title: string, contentUrl: string}>}
     */
    public function listPages(Authenticatable $actor, string $pagesUrl, string $notebookWebUrl = '', string $fallbackOwnerId = ''): array;

    public function getPageHtml(Authenticatable $actor, string $contentUrl): string;
}
