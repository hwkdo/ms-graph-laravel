<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Support\OnenoteGraphUrl;

it('behält die seitenadresse am abschnitt', function (): void {
    $sectionsUrl = 'https://graph.microsoft.com/v1.0/sites/hwkdoedu-my.sharepoint.com,site,web/onenote/notebooks/nb-1/sections';
    $flatPagesUrl = 'https://graph.microsoft.com/v1.0/sites/hwkdoedu-my.sharepoint.com,site,web/onenote/sections/sec-1/pages';

    expect(OnenoteGraphUrl::sectionPagesUrl($sectionsUrl, 'sec-1', $flatPagesUrl))
        ->toBe($flatPagesUrl);
});

it('liest den besitzer aus der persönlichen onedrive-adresse', function (): void {
    $webUrl = 'https://hwkdoedu-my.sharepoint.com/personal/filer_hwkdoedu_onmicrosoft_com/_layouts/15/Doc.aspx?sourcedoc=%7B71394e2d-f51a-4391-b40b-b7f9def13ab1%7D';

    expect(OnenoteGraphUrl::personalOwnerUpn($webUrl))->toBe('filer@hwkdoedu.onmicrosoft.com');
});

it('fragt seiten eines persönlichen notizbuchs über den besitzer ab', function (): void {
    $flatPagesUrl = 'https://graph.microsoft.com/v1.0/sites/hwkdoedu-my.sharepoint.com,807cfae9-318a-4a19-a8d8-3b79062fbb55,bb7bf71f-ff20-4e63-8115-63d075a96c50/onenote/sections/1-3127b475-80f8-45d2-a708-aa5edc8e5472/pages';

    expect(OnenoteGraphUrl::pageListCandidates($flatPagesUrl, 'filer@hwkdoedu.onmicrosoft.com'))->toBe([
        'https://graph.microsoft.com/v1.0/users/filer%40hwkdoedu.onmicrosoft.com/onenote/sections/1-3127b475-80f8-45d2-a708-aa5edc8e5472/pages',
        $flatPagesUrl,
        'https://graph.microsoft.com/v1.0/me/onenote/sections/1-3127b475-80f8-45d2-a708-aa5edc8e5472/pages',
    ]);
});

it('ersetzt die ungültige notizbuch-seitenadresse durch den besitzer', function (): void {
    $nestedPagesUrl = 'https://graph.microsoft.com/v1.0/sites/hwkdoedu-my.sharepoint.com,site,web/onenote/notebooks/nb-1/sections/sec-1/pages';

    expect(OnenoteGraphUrl::pageListCandidates($nestedPagesUrl, 'filer@hwkdoedu.onmicrosoft.com'))->toBe([
        'https://graph.microsoft.com/v1.0/users/filer%40hwkdoedu.onmicrosoft.com/onenote/sections/sec-1/pages',
        'https://graph.microsoft.com/v1.0/sites/hwkdoedu-my.sharepoint.com,site,web/onenote/sections/sec-1/pages',
        'https://graph.microsoft.com/v1.0/me/onenote/sections/sec-1/pages',
    ]);
});

it('holt den seiteninhalt über die flache pages-adresse', function (): void {
    $nested = 'https://graph.microsoft.com/v1.0/users/filer@hwkdoedu.onmicrosoft.com/onenote/sections/sec-1/pages/page!sec-1/content';
    $flat = 'https://graph.microsoft.com/v1.0/users/filer@hwkdoedu.onmicrosoft.com/onenote/pages/page!sec-1/content';

    expect(OnenoteGraphUrl::pageContentUrl('', 'page!sec-1', $nested))->toBe($flat)
        ->and(OnenoteGraphUrl::pageContentUrl(
            'https://graph.microsoft.com/v1.0/users/filer@hwkdoedu.onmicrosoft.com/onenote/sections/sec-1/pages',
            'page!sec-1',
            $flat,
        ))->toBe($flat);
});
