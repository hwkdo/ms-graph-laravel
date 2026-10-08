<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Interfaces\OnenoteDelegatedTokenInterface;
use Hwkdo\MsGraphLaravel\Services\OneNoteService;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

it('lehnt onenote adressen ausserhalb von graph ab', function (): void {
    $service = new OneNoteService(Mockery::mock(OnenoteDelegatedTokenInterface::class));
    $actor = Mockery::mock(Authenticatable::class);

    expect(fn () => $service->listSections($actor, 'https://example.com/onenote/sections', ''))
        ->toThrow(InvalidArgumentException::class, 'Ungültige OneNote-Adresse.');
});
