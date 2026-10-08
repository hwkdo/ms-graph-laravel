<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Support;

class OnenoteGraphUrl
{
    public static function sectionPagesUrl(string $sectionsRequestUrl, string $sectionId, string $fallbackPagesUrl, string $parentNotebookSelf = ''): string
    {
        return self::canonicalSectionPagesUrl(trim($fallbackPagesUrl));
    }

    public static function canonicalSectionPagesUrl(string $pagesUrl): string
    {
        $canonical = preg_replace(
            '#/onenote/(?:notebooks|sectionGroups)/[^/]+/sections/([^/]+)/pages$#',
            '/onenote/sections/$1/pages',
            trim($pagesUrl),
        );

        return is_string($canonical) ? $canonical : trim($pagesUrl);
    }

    public static function personalOwnerUpn(string $webUrl): ?string
    {
        $webUrl = (string) preg_replace('/^onenote:/i', '', trim($webUrl));
        $parts = parse_url($webUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if (! str_ends_with($host, '-my.sharepoint.com') || preg_match('#/personal/([^/]+)#', $path, $matches) !== 1) {
            return null;
        }

        $tenant = substr($host, 0, -strlen('-my.sharepoint.com'));
        $suffix = '_'.$tenant.'_onmicrosoft_com';
        $slug = $matches[1];
        if ($tenant === '' || ! str_ends_with($slug, $suffix)) {
            return null;
        }

        $local = substr($slug, 0, -strlen($suffix));
        if ($local === '') {
            return null;
        }

        return $local.'@'.$tenant.'.onmicrosoft.com';
    }

    public static function siteKeyFromPagesUrl(string $pagesUrl): ?string
    {
        $path = (string) (parse_url($pagesUrl, PHP_URL_PATH) ?? '');
        if (preg_match('#/sites/([^/]+)/onenote/#', $path, $matches) !== 1) {
            return null;
        }

        $siteKey = rawurldecode($matches[1]);

        return str_contains($siteKey, '-my.sharepoint.com') ? $siteKey : null;
    }

    /**
     * @return list<string>
     */
    public static function pageListCandidates(string $pagesUrl, ?string $ownerUserId = null, ?string $fallbackOwnerId = null): array
    {
        $pagesUrl = self::canonicalSectionPagesUrl($pagesUrl);
        if ($pagesUrl === '') {
            return [];
        }

        $origin = self::graphOrigin($pagesUrl);
        $sectionId = self::sectionIdFromPagesUrl($pagesUrl);
        $candidates = [];

        foreach ([$ownerUserId, $fallbackOwnerId] as $owner) {
            if ($origin === null || $sectionId === null || ! is_string($owner) || $owner === '') {
                continue;
            }

            $candidates[] = $origin.'/users/'.rawurlencode($owner).'/onenote/sections/'.$sectionId.'/pages';
        }

        $candidates[] = $pagesUrl;

        if ($origin !== null && $sectionId !== null && self::siteKeyFromPagesUrl($pagesUrl) !== null) {
            $candidates[] = $origin.'/me/onenote/sections/'.$sectionId.'/pages';
        }

        return array_values(array_unique($candidates));
    }

    private static function graphOrigin(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = (string) ($parts['scheme'] ?? '');
        $host = (string) ($parts['host'] ?? '');
        $path = (string) ($parts['path'] ?? '');
        if ($scheme === '' || $host === '' || preg_match('#^(/(?:v1\.0|beta))/#', $path, $matches) !== 1) {
            return null;
        }

        return $scheme.'://'.$host.$matches[1];
    }

    private static function sectionIdFromPagesUrl(string $pagesUrl): ?string
    {
        $path = (string) (parse_url($pagesUrl, PHP_URL_PATH) ?? '');
        if (preg_match('#/onenote/(?:notebooks/[^/]+/)?sections/([^/]+)/pages$#', $path, $matches) !== 1) {
            return null;
        }

        return rawurldecode($matches[1]);
    }

    public static function pageContentUrl(string $pagesRequestUrl, string $pageId, string $fallbackContentUrl): string
    {
        $pageId = trim($pageId);
        $fallback = self::flattenPageContentUrl(trim($fallbackContentUrl));
        if ($fallback !== '') {
            return $fallback;
        }

        $base = rtrim(trim($pagesRequestUrl), '/');
        if ($pageId === '' || $base === '') {
            return '';
        }

        $rewritten = preg_replace(
            '#/onenote/.+/pages$#',
            '/onenote/pages/'.rawurlencode($pageId).'/content',
            $base,
        );

        return is_string($rewritten) ? $rewritten : '';
    }

    private static function flattenPageContentUrl(string $url): string
    {
        if ($url === '') {
            return '';
        }

        $flattened = preg_replace(
            '#/onenote/(?:sections/[^/]+|(?:notebooks|sectionGroups)/[^/]+/sections/[^/]+)/pages/([^/]+)/content$#',
            '/onenote/pages/$1/content',
            $url,
        );

        return is_string($flattened) ? $flattened : $url;
    }
}
