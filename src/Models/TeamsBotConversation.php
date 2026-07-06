<?php

declare(strict_types=1);

namespace Hwkdo\MsGraphLaravel\Models;

use Hwkdo\MsGraphLaravel\Enums\TeamsBotConversationStatus;
use Illuminate\Database\Eloquent\Model;

class TeamsBotConversation extends Model
{
    protected $guarded = [];

    protected $table = 'ms_graph_laravel_teams_bot_conversations';

    protected function casts(): array
    {
        return [
            'status' => TeamsBotConversationStatus::class,
            'installed_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function markActive(string $conversationId, string $serviceUrl, ?string $tenantId = null): void
    {
        $this->update([
            'conversation_id' => $conversationId,
            'service_url' => $serviceUrl,
            'tenant_id' => $tenantId,
            'status' => TeamsBotConversationStatus::Active,
            'installed_at' => $this->installed_at ?? now(),
            'last_error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => TeamsBotConversationStatus::Failed,
            'last_error' => $error,
        ]);
    }

    public function markMessageSent(): void
    {
        $this->update([
            'last_message_at' => now(),
            'last_error' => null,
        ]);
    }

    public function isReadyForMessaging(): bool
    {
        return $this->status === TeamsBotConversationStatus::Active
            && filled($this->conversation_id)
            && filled($this->service_url);
    }
}
