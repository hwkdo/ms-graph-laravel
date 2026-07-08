<?php

declare(strict_types=1);

use Hwkdo\MsGraphLaravel\Data\TeamsBotIncomingMessage;
use Hwkdo\MsGraphLaravel\Support\TeamsActivityContentParser;

it('extracts quoted text from teams html blockquote', function (): void {
    $activity = [
        'text' => <<<'HTML'
<blockquote itemscope itemtype="http://schema.org/Message">
<div itemprop="sender" itemscope itemtype="http://schema.org/Person">
<span itemprop="name">Anna Beispiel</span>
</div>
<div itemprop="comment">Der Drucker im 2. OG druckt nur leere Seiten.</div>
</blockquote>
<p><at>Bot</at> erstelle ein Ticket dafür</p>
HTML,
    ];

    $parsed = TeamsActivityContentParser::parse($activity);

    expect($parsed['quotedText'])->toBe('Der Drucker im 2. OG druckt nur leere Seiten.')
        ->and($parsed['quotedSenderName'])->toBe('Anna Beispiel')
        ->and($parsed['text'])->toBe('erstelle ein Ticket dafür');
});

it('extracts quoted text from messageReference attachments', function (): void {
    $activity = [
        'text' => '<at>Bot</at> erstelle ein Ticket dafür',
        'attachments' => [
            [
                'contentType' => 'messageReference',
                'content' => json_encode([
                    'messageId' => '1622853091207',
                    'messagePreview' => 'WLAN im Besprechungsraum fällt ständig aus',
                    'messageSender' => [
                        'user' => [
                            'id' => '8ea0e38b-efb3-4757-924a-5f94061cf8c2',
                            'displayName' => 'Max Mustermann',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
        ],
    ];

    $parsed = TeamsActivityContentParser::parse($activity);

    expect($parsed['quotedText'])->toBe('WLAN im Besprechungsraum fällt ständig aus')
        ->and($parsed['quotedSenderName'])->toBe('Max Mustermann')
        ->and($parsed['quotedSenderAzureId'])->toBe('8ea0e38b-efb3-4757-924a-5f94061cf8c2')
        ->and($parsed['text'])->toBe('erstelle ein Ticket dafür');
});

it('extracts quoted sender azure id from quotedReply entities', function (): void {
    $activity = [
        'text' => 'erstelle ein Ticket dafür',
        'entities' => [
            [
                'type' => 'quotedReply',
                'quotedReply' => [
                    'messageId' => '1772050244573',
                    'senderId' => '29:18ea0e38b-efb3-4757-924a-5f94061cf8c2',
                    'senderName' => 'Support Kollege',
                    'preview' => 'Monitor bleibt schwarz nach dem Login',
                ],
            ],
        ],
    ];

    $parsed = TeamsActivityContentParser::parse($activity);

    expect($parsed['quotedSenderAzureId'])->toBe('8ea0e38b-efb3-4757-924a-5f94061cf8c2');
});

it('ignores opaque teams member ids from quotedReply when no azure uuid is present', function (): void {
    $activity = [
        'text' => 'erstelle ein Ticket',
        'entities' => [
            [
                'type' => 'quotedReply',
                'quotedReply' => [
                    'messageId' => '1783419459151',
                    'senderId' => '29:1fPTMf5plhfiwflKtjumGwApZDf_Q5ouA8jdhKCH5loRiU1nV1Sc1WyJXARTxxKHk_h7CZdrUzRJQRgKej6E8tw',
                    'senderName' => 'Lubritz, Markus',
                    'preview' => 'Kann doch bitte einfach einer den Stecker ziehen',
                ],
            ],
        ],
    ];

    expect(TeamsActivityContentParser::parse($activity)['quotedSenderAzureId'])->toBeNull();
});

it('extracts azure uuid from skype reply html in group chats', function (): void {
    $activity = [
        'text' => '<quoted messageId="1783419459151"/><at>HWKDO Intranet Bot (local)</at> erstelle ein ticket',
        'attachments' => [
            [
                'contentType' => 'text/html',
                'content' => <<<'HTML'
<blockquote itemscope itemtype="http://schema.skype.com/Reply" itemid="1783419459151">
<strong itemprop="mri" itemid="8:orgid:548ef459-e27e-42ac-beda-9c846ca1259a">Lubritz, Markus</strong>
<span itemprop="time" itemid="1783419459151"></span>
<p itemprop="preview">Kann doch bitte einfach einer den Stecker ziehen und das Ding kaltstarten bitte</p>
</blockquote>
<p><span itemtype="http://schema.skype.com/Mention" itemscope="" itemid="0">HWKDO Intranet Bot (local)</span>&nbsp;erstelle ein ticket</p>
HTML,
            ],
        ],
        'entities' => [
            [
                'type' => 'quotedReply',
                'quotedReply' => [
                    'messageId' => '1783419459151',
                    'senderId' => '29:1fPTMf5plhfiwflKtjumGwApZDf_Q5ouA8jdhKCH5loRiU1nV1Sc1WyJXARTxxKHk_h7CZdrUzRJQRgKej6E8tw',
                    'senderName' => 'Lubritz, Markus',
                    'preview' => 'Kann doch bitte einfach einer den Stecker ziehen und das Ding kaltstarten bitte',
                ],
            ],
        ],
    ];

    $parsed = TeamsActivityContentParser::parse($activity);

    expect($parsed['text'])->toBe('erstelle ein ticket')
        ->and($parsed['quotedText'])->toBe('Kann doch bitte einfach einer den Stecker ziehen und das Ding kaltstarten bitte')
        ->and($parsed['quotedSenderName'])->toBe('Lubritz, Markus')
        ->and($parsed['quotedSenderAzureId'])->toBe('548ef459-e27e-42ac-beda-9c846ca1259a')
        ->and($parsed['quotedMessageId'])->toBe('1783419459151');
});

it('extracts quoted message id from image quote payload', function (): void {
    $activity = [
        'text' => '<quoted messageId="1783060714709"/><at>HWKDO Intranet Bot (local)</at> erstelle ein ticket',
        'attachments' => [
            [
                'contentType' => 'text/html',
                'content' => <<<'HTML'
<blockquote itemscope itemtype="http://schema.skype.com/Reply" itemid="1783060714709">
<strong itemprop="mri" itemid="8:orgid:7d03a3be-f8ca-4ac5-b22d-5e3ae0f12816">Krüger, Kirsten</strong>
<p itemprop="preview">fakt ist wir wollen das genauso machen! 📷 steckt also doch da drin</p>
</blockquote>
HTML,
            ],
        ],
        'entities' => [
            [
                'type' => 'quotedReply',
                'quotedReply' => [
                    'messageId' => '1783060714709',
                    'senderName' => 'Krüger, Kirsten',
                    'preview' => 'fakt ist wir wollen das genauso machen! 📷 steckt also doch da drin',
                ],
            ],
        ],
    ];

    $parsed = TeamsActivityContentParser::parse($activity);

    expect($parsed['quotedMessageId'])->toBe('1783060714709')
        ->and($parsed['quotedSenderAzureId'])->toBe('7d03a3be-f8ca-4ac5-b22d-5e3ae0f12816');
});

it('merges html quote text with sender azure id from messageReference metadata', function (): void {
    $activity = [
        'text' => <<<'HTML'
<blockquote>
<div itemprop="comment">Mein Computer ist kaputt</div>
</blockquote>
<p>erstelle ein Ticket dafür</p>
HTML,
        'attachments' => [
            [
                'contentType' => 'messageReference',
                'content' => json_encode([
                    'messagePreview' => 'Mein Computer ist kaputt',
                    'messageSender' => [
                        'user' => [
                            'id' => '11111111-2222-3333-4444-555555555555',
                            'displayName' => 'User A',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
        ],
    ];

    $parsed = TeamsActivityContentParser::parse($activity);

    expect($parsed['quotedText'])->toBe('Mein Computer ist kaputt')
        ->and($parsed['quotedSenderAzureId'])->toBe('11111111-2222-3333-4444-555555555555');
});

it('extracts quoted text from quotedReply entities', function (): void {
    $activity = [
        'text' => 'erstelle ein Ticket dafür',
        'entities' => [
            [
                'type' => 'quotedReply',
                'quotedReply' => [
                    'messageId' => '1772050244573',
                    'senderName' => 'Support Kollege',
                    'preview' => 'Monitor bleibt schwarz nach dem Login',
                ],
            ],
        ],
    ];

    $parsed = TeamsActivityContentParser::parse($activity);

    expect($parsed['quotedText'])->toBe('Monitor bleibt schwarz nach dem Login')
        ->and($parsed['quotedSenderName'])->toBe('Support Kollege');
});

it('extracts quoted text from html attachments when activity text is plain', function (): void {
    $activity = [
        'text' => 'erstelle mir ein ticket dafür',
        'attachments' => [
            [
                'contentType' => 'text/html',
                'content' => <<<'HTML'
<blockquote itemscope itemtype="http://schema.org/Message">
<div itemprop="sender" itemscope itemtype="http://schema.org/Person">
<span itemprop="name">Lubritz, Markus</span>
</div>
<div itemprop="comment">mein computer geht nicht mehr an, es piept nur noch und leuchtet rot</div>
</blockquote>
<p>erstelle mir ein ticket dafür</p>
HTML,
            ],
            [
                'contentType' => 'messageReference',
                'content' => json_encode([
                    'messagePreview' => 'mein computer geht nicht mehr an, es piept nur noch und leuchtet rot',
                    'messageSender' => [
                        'user' => [
                            'id' => '9d0ba845-db64-4977-9f43-3a244a4dab1c',
                            'displayName' => 'Lubritz, Markus',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
        ],
    ];

    $parsed = TeamsActivityContentParser::parse($activity);

    expect($parsed['text'])->toBe('erstelle mir ein ticket dafür')
        ->and($parsed['quotedText'])->toContain('computer geht nicht mehr an')
        ->and($parsed['quotedSenderName'])->toBe('Lubritz, Markus')
        ->and($parsed['quotedSenderAzureId'])->toBe('9d0ba845-db64-4977-9f43-3a244a4dab1c');
});

it('extracts quoted sender from strong tags inside blockquote', function (): void {
    $activity = [
        'text' => <<<'HTML'
<blockquote>
<strong>Max Mustermann</strong>
<div itemprop="comment">WLAN im Besprechungsraum fällt ständig aus</div>
</blockquote>
<p>erstelle ein Ticket dafür</p>
HTML,
    ];

    $parsed = TeamsActivityContentParser::parse($activity);

    expect($parsed['quotedSenderName'])->toBe('Max Mustermann')
        ->and($parsed['quotedText'])->toBe('WLAN im Besprechungsraum fällt ständig aus');
});

it('extracts quoted text from skype forward payloads without sender metadata', function (): void {
    $activity = [
        'text' => "erstelle ein ticket\n\nHallo Alex, auf folgender Seite stehen noch falsche Informationen",
        'attachments' => [
            [
                'contentType' => 'text/html',
                'content' => <<<'HTML'
<p>erstelle ein ticket</p>
<blockquote itemtype="http://schema.skype.com/Forward">
<p>Hallo Alex, auf folgender Seite stehen noch falsche Informationen/wurden nicht aktualisiert: Kunststofftechnik (HWK-MA -&gt; Seminarplugin) - Handwerkskammer Dortmund</p>
<p>Ich wollte gerade ein Webchange Ticket öffnen, gibt es da Probleme wenn ich sage, dass es mit dir Abgestimmt ist? Man muss da jemanden angeben</p>
</blockquote>
HTML,
            ],
        ],
    ];

    $parsed = TeamsActivityContentParser::parse($activity);

    expect($parsed['text'])->toBe('erstelle ein ticket')
        ->and($parsed['quotedText'])->toContain('Hallo Alex')
        ->and($parsed['quotedText'])->toContain('Webchange Ticket')
        ->and($parsed['quotedSenderName'])->toBeNull()
        ->and($parsed['quotedSenderAzureId'])->toBeNull()
        ->and($parsed['quotedMessageId'])->toBeNull();
});

it('extracts sender data from forwardedMessageReference attachments', function (): void {
    $activity = [
        'text' => 'erstelle ein Ticket dafür',
        'attachments' => [
            [
                'contentType' => 'forwardedMessageReference',
                'content' => json_encode([
                    'originalMessageId' => '1783513803078',
                    'originalMessageContent' => '<p>Hallo Alex, bitte die Seite aktualisieren.</p>',
                    'originalConversationId' => '19:example@thread.v2',
                    'originalMessageSender' => [
                        'user' => [
                            'id' => '9d0ba845-db64-4977-9f43-3a244a4dab1c',
                            'displayName' => 'Lubritz, Markus',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            ],
        ],
    ];

    $parsed = TeamsActivityContentParser::parse($activity);

    expect($parsed['quotedText'])->toBe('Hallo Alex, bitte die Seite aktualisieren.')
        ->and($parsed['quotedSenderName'])->toBe('Lubritz, Markus')
        ->and($parsed['quotedSenderAzureId'])->toBe('9d0ba845-db64-4977-9f43-3a244a4dab1c');
});

it('builds incoming messages with quoted content from webhook payload', function (): void {
    $message = TeamsBotIncomingMessage::fromWebhook(
        'message',
        [
            'text' => '<blockquote><div itemprop="comment">Tastatur reagiert nicht mehr</div></blockquote><p>erstelle ein Ticket dafür</p>',
            'conversation' => ['id' => 'conv-dm', 'conversationType' => 'personal'],
            'from' => ['userPrincipalName' => 'max@example.com', 'name' => 'Max Mustermann'],
            'id' => 'msg-1',
        ],
        ['conversationId' => 'conv-dm', 'userAadId' => 'azure-max'],
        'azure-max',
    );

    expect($message->text)->toBe('erstelle ein Ticket dafür')
        ->and($message->quotedText)->toBe('Tastatur reagiert nicht mehr')
        ->and($message->hasQuotedContent())->toBeTrue();
});
