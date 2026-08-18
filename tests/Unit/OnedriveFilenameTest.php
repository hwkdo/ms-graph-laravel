<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Support\OnedriveFilename;

it('behaelt den originalen dateinamen inklusive punkt', function (): void {
    expect(OnedriveFilename::sanitize('test.pdf'))->toBe('test.pdf');
});

it('behaelt weitere punkte und die original-schreibweise', function (): void {
    expect(OnedriveFilename::sanitize('Test.Backup.PDF'))->toBe('Test.Backup.PDF')
        ->and(OnedriveFilename::sanitize('Mein Dokument.pdf'))->toBe('Mein Dokument.pdf');
});

it('entfernt pfadanteile und ungueltige onedrive-zeichen', function (): void {
    expect(OnedriveFilename::sanitize('../secret/test.pdf'))->toBe('test.pdf')
        ->and(OnedriveFilename::sanitize('bericht:final?.pdf'))->toBe('berichtfinal.pdf');
});

it('faellt auf einen sicheren namen zurueck wenn nichts uebrig bleibt', function (): void {
    expect(OnedriveFilename::sanitize('***'))->toBe('upload')
        ->and(OnedriveFilename::sanitize('...'))->toBe('upload');
});
