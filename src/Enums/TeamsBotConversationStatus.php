<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Enums;

enum TeamsBotConversationStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Failed = 'failed';
    case Uninstalled = 'uninstalled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Ausstehend',
            self::Active => 'Aktiv',
            self::Failed => 'Fehlgeschlagen',
            self::Uninstalled => 'Deinstalliert',
        };
    }
}
