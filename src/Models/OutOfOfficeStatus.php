<?php

namespace Hwkdo\MsGraphLaravel\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutOfOfficeStatus extends Model
{
    protected $guarded = [];

    protected $table = 'ms_graph_laravel_out_of_office_stati';

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine if the user is currently out of office.
     */
    public function isOutOfOffice(): bool
    {
        if (! $this->status) {
            return false;
        }

        return match ($this->status) {
            'alwaysEnabled' => true,
            'scheduled' => $this->scheduled_start_at && $this->scheduled_end_at
                ? now()->between($this->scheduled_start_at, $this->scheduled_end_at)
                : false,
            default => false,
        };
    }

    /**
     * Get formatted status data similar to getOutOfOffice() in hasAzureMailbox trait.
     */
    public function getFormattedStatus(): array
    {
        if (! $this->status) {
            return [
                'isOutOfOffice' => null,
                'status' => null,
                'start_d' => null,
                'start_dt' => null,
                'end_d' => null,
                'end_dt' => null,
            ];
        }

        $isOutOfOffice = $this->isOutOfOffice();

        return [
            'isOutOfOffice' => $isOutOfOffice,
            'status' => $this->status,
            'start_d' => $this->scheduled_start_at?->format('d.m.Y'),
            'start_dt' => $this->scheduled_start_at?->format('d.m.Y H:i').' Uhr',
            'end_d' => $this->scheduled_end_at?->format('d.m.Y'),
            'end_dt' => $this->scheduled_end_at?->format('d.m.Y H:i').' Uhr',
        ];
    }
}
