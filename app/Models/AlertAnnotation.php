<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AlertAnnotation extends Model
{
    protected $fillable = [
        'alert_uid', 'alert_index', 'status', 'incident_id', 'ticket_id', 'assignee_id',
        'agent_name', 'rule_id', 'rule_description', 'rule_level', 'alert_timestamp',
    ];

    protected $casts = [
        'alert_timestamp' => 'datetime',
        'rule_level' => 'integer',
    ];

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'alert_annotation_label');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AlertComment::class)->latest();
    }

    /**
     * The unified report this alert belongs to.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Fetch annotations for a set of alert UIDs keyed by UID, for list views.
     */
    public static function forUids(array $uids): array
    {
        if ($uids === []) {
            return [];
        }

        return static::with(['labels', 'assignee:id,name', 'ticket:id,reference,title'])
            ->whereIn('alert_uid', $uids)
            ->get()
            ->keyBy('alert_uid')
            ->all();
    }
}
