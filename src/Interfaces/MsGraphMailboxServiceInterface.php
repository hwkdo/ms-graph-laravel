<?php

namespace Hwkdo\MsGraphLaravel\Interfaces;

use Illuminate\Support\Carbon;

interface MsGraphMailboxServiceInterface
{
    public const INTRANET_AUSTRITT_FORWARD_RULE_NAME = 'Intranet Austritt Weiterleitung';

    public function getAutoReplySettings($username, $raw = true);

    public function getSettings($username);

    public function setOutOfOffice($upn, $message, ?Carbon $von = null, ?Carbon $bis = null);

    public function removeOutOfOffice($username);

    /**
     * Setzt eine Inbox-Weiterleitungsregel (forwardTo) auf der Mailbox.
     * Bestehende Regel mit gleichem Anzeigenamen wird vorher entfernt.
     */
    public function setInboxForwarding(string $mailboxUpn, string $forwardToSmtp): void;

    /**
     * Entfernt die Intranet-Austritt-Weiterleitungsregel, falls vorhanden.
     */
    public function clearInboxForwarding(string $mailboxUpn): void;
}
