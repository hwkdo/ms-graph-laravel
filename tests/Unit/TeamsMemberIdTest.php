<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Support\TeamsMemberId;

it('extracts azure user id from teams member id', function (): void {
    expect(TeamsMemberId::resolveAzureUserId('29:102bd2b59-d49b-44ce-a709-580a54e1eaf8'))
        ->toBe('02bd2b59-d49b-44ce-a709-580a54e1eaf8');
});

it('detects hi command variants', function (): void {
    expect(TeamsMemberId::isHiCommand('Hi'))->toBeTrue()
        ->and(TeamsMemberId::isHiCommand('hello'))->toBeTrue()
        ->and(TeamsMemberId::isHiCommand('Hallo'))->toBeTrue()
        ->and(TeamsMemberId::isHiCommand('status'))->toBeFalse();
});

it('normalizes teams message text', function (): void {
    $text = TeamsMemberId::normalizeMessageText([
        'text' => '<at>Bot</at> Hi',
    ]);

    expect($text)->toBe('Hi');
});
