<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketItem extends Model
{
    public const KIND_ALERT = 'alert';

    public const KIND_VULNERABILITY = 'vulnerability';

    public const KIND_SCA = 'sca_check';

    protected $fillable = [
        'ticket_id', 'kind', 'ref_key', 'agent_name', 'title', 'severity',
        'occurred_at', 'meta',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'meta' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
