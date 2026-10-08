<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Services;

use Hwkdo\MsGraphLaravel\Exceptions\OneNoteGraphRequestException;
use Hwkdo\MsGraphLaravel\Authentication\OnenoteAccountAuthenticationProvider;
use Hwkdo\MsGraphLaravel\Interfaces\MsGraphOneNoteServiceInterface;
use Hwkdo\MsGraphLaravel\Interfaces\OnenoteDelegatedTokenInterface;
use Hwkdo\MsGraphLaravel\Support\GraphExceptionMessage;
use Hwkdo\MsGraphLaravel\Support\OnenoteGraphUrl;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;
use Microsoft\Graph\Generated\Groups\Item\Onenote\OnenoteRequestBuilder as GroupOnenoteRequestBuilder;
use Microsoft\Graph\Generated\Models\CopyNotebookModel;
use Microsoft\Graph\Generated\Models\Notebook;
use Microsoft\Graph\Generated\Users\Item\Onenote\Notebooks\GetNotebookFromWebUrl\GetNotebookFromWebUrlPostRequestBody;
use Microsoft\Graph\Generated\Models\ODataErrors\ODataError;
use Microsoft\Graph\Generated\Models\OnenotePageCollectionResponse;
use Microsoft\Graph\Generated\Models\OnenoteSectionCollectionResponse;
use Microsoft\Graph\Generated\Models\SectionGroupCollectionResponse;
use Microsoft\Graph\Generated\Users\Item\Onenote\OnenoteRequestBuilder as UserOnenoteRequestBuilder;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Kiota\Abstractions\HttpMethod;
use Microsoft\Kiota\Abstractions\RequestInformation;
use Psr\Http\Message\StreamInterface;
use Throwable;

class OneNoteService implements MsGraphOneNoteServiceInterface
{
    public function __construct(
        private OnenoteDelegatedTokenInterface $onenoteTokens,
    ) {}

    /**
     * @return list<array{id: string, name: string, sectionsUrl: string, sectionGroupsUrl: string, webUrl: string, ownerUserId: string}>
     */
    public function listNotebooks(Authenticatable $actor, string $ownerType, string $ownerId): array
    {
        $response = $this->onenote($actor, $ownerType, $ownerId)->notebooks()->get()->wait();

        return $this->mapNotebooks($response?->getValue() ?? []);
    }

    /**
     * @return array{requestUrl: string, sections: list<array{id: string, name: string, pagesUrl: string}>}
     */
    public function listSections(Authenticatable $actor, string $sectionsUrl, string $sectionGroupsUrl, string $webUrl = ''): array
    {
        $resolved = $this->resolveNotebookUrls($actor, $webUrl);
        if ($resolved !== null) {
            [$sectionsUrl, $sectionGroupsUrl] = $resolved;
        }

        $sections = $this->mapSections($this->collection(
            $actor,
            $sectionsUrl,
            OnenoteSectionCollectionResponse::class,
        ), $sectionsUrl);

        if (trim($sectionGroupsUrl) !== '') {
            try {
                $sections = array_merge($sections, $this->sectionsInGroups($actor, $sectionGroupsUrl, '', 0));
            } catch (Throwable) {
                // Abschnittsgruppen bleiben optional, die direkten Abschnitte sind bereits geladen.
            }
        }

        return [
            'requestUrl' => $sectionsUrl,
            'sections' => $sections,
        ];
    }

    /**
     * @return array{requestUrl: string, pages: list<array{id: string, title: string, contentUrl: string}>}
     */
    public function listPages(Authenticatable $actor, string $pagesUrl, string $notebookWebUrl = '', string $fallbackOwnerId = ''): array
    {
        $ownerUserId = OnenoteGraphUrl::personalOwnerUpn($notebookWebUrl) ?? $this->personalSiteOwnerId($actor, $pagesUrl);
        $candidates = OnenoteGraphUrl::pageListCandidates($pagesUrl, $ownerUserId, $fallbackOwnerId);
        $lastException = null;
        $attempts = [];

        foreach ($candidates as $candidate) {
            try {
                return [
                    'requestUrl' => $candidate,
                    'pages' => $this->mapPages($this->collection($actor, $candidate, OnenotePageCollectionResponse::class), $candidate),
                ];
            } catch (OneNoteGraphRequestException $exception) {
                $lastException = $exception;
                $attempts[] = $candidate.' → '.$exception->getMessage();
                if (! $this->isRetriableOnenoteError($exception)) {
                    break;
                }
            }
        }

        if ($lastException instanceof OneNoteGraphRequestException) {
            throw new OneNoteGraphRequestException(
                $candidates[0] ?? $lastException->requestUrl,
                implode("\n", $attempts),
                $lastException,
            );
        }

        throw new InvalidArgumentException('Für diesen Abschnitt hat Graph keine Seitenadresse geliefert.');
    }

    public function getPageHtml(Authenticatable $actor, string $contentUrl): string
    {
        $request = $this->graphRequest($contentUrl);
        $stream = $this->graph()->getRequestAdapter()->sendPrimitiveAsync($request, StreamInterface::class, [
            'XXX' => [ODataError::class, 'createFromDiscriminatorValue'],
        ])->wait();

        if (! $stream instanceof StreamInterface) {
            return '';
        }

        return $stream->getContents();
    }

    private function personalSiteOwnerId(Authenticatable $actor, string $pagesUrl): ?string
    {
        $siteKey = OnenoteGraphUrl::siteKeyFromPagesUrl($pagesUrl);
        if ($siteKey === null) {
            return null;
        }

        $url = 'https://graph.microsoft.com/v1.0/sites/'.$siteKey;

        try {
            $site = $this->graphJson($actor, $url.'?$select=webUrl');
        } catch (Throwable) {
            $site = null;
        }

        $webUrl = is_array($site) ? ($site['webUrl'] ?? null) : null;
        if (is_string($webUrl)) {
            $upn = OnenoteGraphUrl::personalOwnerUpn($webUrl);
            if ($upn !== null) {
                return $upn;
            }
        }

        try {
            $drive = $this->graphJson($actor, $url.'/drive?$select=owner');
        } catch (Throwable) {
            return null;
        }

        $ownerId = is_array($drive) ? ($drive['owner']['user']['id'] ?? null) : null;

        return is_string($ownerId) && $ownerId !== '' ? $ownerId : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function graphJson(Authenticatable $actor, string $url): ?array
    {
        $request = new RequestInformation;
        $request->httpMethod = HttpMethod::GET;
        $request->urlTemplate = '{+baseurl}';
        $request->pathParameters[RequestInformation::$RAW_URL_KEY] = $url;
        $stream = $this->graph()->getRequestAdapter()->sendPrimitiveAsync($request, StreamInterface::class, [
            'XXX' => [ODataError::class, 'createFromDiscriminatorValue'],
        ])->wait();

        if (! $stream instanceof StreamInterface) {
            return null;
        }

        $payload = json_decode($stream->getContents(), true);

        return is_array($payload) ? $payload : null;
    }

    private function isRetriableOnenoteError(OneNoteGraphRequestException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, '40007')
            || str_contains($message, '20102')
            || str_contains($message, 'UnknownError')
            || str_contains($message, 'No HTTP resource was found');
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function resolveNotebookUrls(Authenticatable $actor, string $webUrl): ?array
    {
        $webUrl = trim($webUrl);
        if ($webUrl === '') {
            return null;
        }

        $body = new GetNotebookFromWebUrlPostRequestBody;
        $body->setWebUrl($webUrl);

        try {
            $notebook = $this->graph()
                ->me()
                ->onenote()
                ->notebooks()
                ->getNotebookFromWebUrl()
                ->post($body)
                ->wait();
        } catch (Throwable) {
            return null;
        }

        if (! $notebook instanceof CopyNotebookModel) {
            return null;
        }

        $sectionsUrl = trim((string) $notebook->getSectionsUrl());
        if ($sectionsUrl === '') {
            return null;
        }

        return [$sectionsUrl, trim((string) $notebook->getSectionGroupsUrl())];
    }

    /**
     * @return list<array{id: string, name: string, pagesUrl: string}>
     */
    private function sectionsInGroups(Authenticatable $actor, string $groupsUrl, string $prefix, int $depth): array
    {
        if ($depth > 5) {
            return [];
        }

        $sections = [];
        $groups = $this->collection($actor, $groupsUrl, SectionGroupCollectionResponse::class);

        foreach ($groups as $group) {
            $name = trim((string) $group->getDisplayName());
            $label = $prefix !== '' && $name !== '' ? $prefix.' / '.$name : ($name !== '' ? $name : $prefix);
            $sectionsUrl = trim((string) $group->getSectionsUrl());

            if ($sectionsUrl !== '') {
                foreach ($this->mapSections($this->collection($actor, $sectionsUrl, OnenoteSectionCollectionResponse::class), $sectionsUrl) as $section) {
                    $sections[] = [
                        'id' => $section['id'],
                        'name' => $label !== '' ? $label.' / '.$section['name'] : $section['name'],
                        'pagesUrl' => $section['pagesUrl'],
                    ];
                }
            }

            $nestedUrl = trim((string) $group->getSectionGroupsUrl());
            if ($nestedUrl !== '') {
                $sections = array_merge($sections, $this->sectionsInGroups($actor, $nestedUrl, $label, $depth + 1));
            }
        }

        return $sections;
    }

    /**
     * @param  class-string<OnenoteSectionCollectionResponse|SectionGroupCollectionResponse|OnenotePageCollectionResponse>  $responseClass
     * @return list<object>
     */
    private function collection(Authenticatable $actor, string $url, string $responseClass): array
    {
        $items = [];
        $next = $url;
        $page = 0;

        while ($next !== '' && $page < 20) {
            try {
                $response = $this->graph()->getRequestAdapter()->sendAsync(
                    $this->graphRequest($next),
                    [$responseClass, 'createFromDiscriminatorValue'],
                    ['XXX' => [ODataError::class, 'createFromDiscriminatorValue']],
                )->wait();
            } catch (InvalidArgumentException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw new OneNoteGraphRequestException(
                    $next,
                    GraphExceptionMessage::resolve($exception, 'OneNote-Abruf fehlgeschlagen.'),
                    $exception,
                );
            }

            foreach ($response?->getValue() ?? [] as $item) {
                $items[] = $item;
            }

            $next = trim((string) $response?->getOdataNextLink());
            $page++;
        }

        return $items;
    }

    private function graphRequest(string $url): RequestInformation
    {
        $this->assertGraphOnenoteUrl($url);

        $request = new RequestInformation;
        $request->httpMethod = HttpMethod::GET;
        $request->urlTemplate = '{+baseurl}';
        $request->pathParameters[RequestInformation::$RAW_URL_KEY] = $url;

        return $request;
    }

    private function assertGraphOnenoteUrl(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if ($host !== 'graph.microsoft.com' || ! str_contains($path, '/onenote/')) {
            throw new InvalidArgumentException('Ungültige OneNote-Adresse.');
        }
    }

    private function graph(): GraphServiceClient
    {
        return GraphServiceClient::createWithAuthenticationProvider(
            new OnenoteAccountAuthenticationProvider($this->onenoteTokens),
        );
    }

    private function onenote(Authenticatable $actor, string $ownerType, string $ownerId): UserOnenoteRequestBuilder|GroupOnenoteRequestBuilder
    {
        $ownerId = trim($ownerId);
        if ($ownerId === '') {
            throw new InvalidArgumentException('OneNote-Besitzer fehlt.');
        }

        $graph = $this->graph();

        return match ($ownerType) {
            'user' => $graph->users()->byUserId($ownerId)->onenote(),
            'group' => $graph->groups()->byGroupId($ownerId)->onenote(),
            default => throw new InvalidArgumentException('OneNote-Quelle muss user oder group sein.'),
        };
    }

    /**
     * @param  iterable<int, mixed>  $items
     * @return list<array{id: string, name: string, sectionsUrl: string, sectionGroupsUrl: string, webUrl: string, ownerUserId: string}>
     */
    private function mapNotebooks(iterable $items): array
    {
        $mapped = [];

        foreach ($items as $item) {
            if (! $item instanceof Notebook) {
                continue;
            }

            $id = $item->getId();
            if (! is_string($id) || $id === '') {
                continue;
            }

            $sectionsUrl = trim((string) $item->getSectionsUrl());

            $links = $item->getLinks();
            $webUrl = trim((string) $links?->getOneNoteWebUrl()?->getHref());
            if ($webUrl === '') {
                $webUrl = trim((string) $links?->getOneNoteClientUrl()?->getHref());
            }

            $ownerUserId = trim((string) $item->getCreatedBy()?->getUser()?->getId());
            $name = trim((string) $item->getDisplayName());
            $mapped[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : $id,
                'sectionsUrl' => $sectionsUrl,
                'sectionGroupsUrl' => trim((string) $item->getSectionGroupsUrl()),
                'webUrl' => $webUrl,
                'ownerUserId' => $ownerUserId,
            ];
        }

        return $mapped;
    }

    /**
     * @param  iterable<int, object>  $items
     * @return list<array{id: string, name: string, pagesUrl: string}>
     */
    private function mapSections(iterable $items, string $sectionsRequestUrl): array
    {
        $mapped = [];

        foreach ($items as $item) {
            $id = $item->getId();
            if (! is_string($id) || $id === '') {
                continue;
            }

            $parentSelf = trim((string) $item->getParentNotebook()?->getEscapedSelf());
            $pagesUrl = OnenoteGraphUrl::sectionPagesUrl(
                $sectionsRequestUrl,
                $id,
                trim((string) $item->getPagesUrl()),
                $parentSelf,
            );
            if ($pagesUrl === '') {
                continue;
            }

            $name = trim((string) $item->getDisplayName());
            $mapped[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : $id,
                'pagesUrl' => $pagesUrl,
            ];
        }

        return $mapped;
    }

    /**
     * @param  iterable<int, object>  $items
     * @return list<array{id: string, title: string, contentUrl: string}>
     */
    private function mapPages(iterable $items, string $pagesRequestUrl): array
    {
        $pages = [];

        foreach ($items as $page) {
            $id = $page->getId();
            if (! is_string($id) || $id === '') {
                continue;
            }

            $contentUrl = OnenoteGraphUrl::pageContentUrl($pagesRequestUrl, $id, trim((string) $page->getContentUrl()));
            if ($contentUrl === '') {
                continue;
            }

            $title = trim((string) $page->getTitle());
            $pages[] = [
                'id' => $id,
                'title' => $title !== '' ? $title : 'Ohne Titel',
                'contentUrl' => $contentUrl,
            ];
        }

        return $pages;
    }
}
