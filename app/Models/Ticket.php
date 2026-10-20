<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    /**
     * One vocabulary for every kind of work, so a single queue can hold alert
     * reports, vulnerability reviews and configuration findings.
     */
    public const STATUSES = [
        'not_reviewed' => ['label' => 'Not Reviewed', 'color' => '#64748b', 'open' => true, 'muted' => true],
        'open' => ['label' => 'Open', 'color' => '#f43f5e', 'open' => true],
        'assigned' => ['label' => 'Assigned', 'color' => '#38bdf8', 'open' => true],
        'in_review' => ['label' => 'In Review', 'color' => '#fbbf24', 'open' => true],
        'remediating' => ['label' => 'Remediating', 'color' => '#fb923c', 'open' => true],
        'verified' => ['label' => 'Verified Fixed', 'color' => '#10b981', 'open' => false],
        'closed' => ['label' => 'Closed', 'color' => '#475569', 'open' => false],
        'risk_accepted' => ['label' => 'Risk Accepted', 'color' => '#8b5cf6', 'open' => false],
        'false_positive' => ['label' => 'False Positive', 'color' => '#f97316', 'open' => false],
    ];

    public const TYPES = [
        'incident' => ['label' => 'Incident', 'color' => '#f43f5e', 'icon' => 'alert'],
        'vulnerability' => ['label' => 'Vulnerability', 'color' => '#fb923c', 'icon' => 'shield'],
        'sca' => ['label' => 'SCA Check', 'color' => '#38bdf8', 'icon' => 'clipboard'],
        'alert' => ['label' => 'Alert', 'color' => '#8b5cf6', 'icon' => 'search'],
    ];

    public const PRIORITIES = [
        'critical' => ['label' => 'Critical', 'color' => '#f43f5e'],
        'high' => ['label' => 'High', 'color' => '#fb923c'],
        'medium' => ['label' => 'Medium', 'color' => '#fbbf24'],
        'low' => ['label' => 'Low', 'color' => '#38bdf8'],
    ];

    public const UNREVIEWED = 'not_reviewed';

    protected $fillable = [
        'reference', 'type', 'title', 'description', 'resolution', 'status', 'priority', 'severity',
        'assignee_id', 'created_by', 'due_at', 'resolved_at', 'closed_at',
        'group_agent', 'group_agents', 'group_rule_id', 'group_window_from', 'group_window_to',
        'group_counted_at', 'meta', 'last_verified_at', 'still_present',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'closed_at' => 'datetime',
        'group_agents' => 'array',
        'group_window_from' => 'datetime',
        'group_window_to' => 'datetime',
        'group_counted_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'still_present' => 'boolean',
        'meta' => 'array',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(TicketItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->latest();
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isGrouped(): bool
    {
        return $this->group_agent !== null && $this->group_rule_id !== null;
    }

    /**
     * Every agent the report tracks. Falls back to the primary agent for rows
     * created before multi-agent grouping existed.
     */
    public function groupedAgents(): array
    {
        $agents = array_values(array_filter((array) ($this->group_agents ?? [])));

        if ($agents === [] && $this->group_agent !== null) {
            $agents = [$this->group_agent];
        }

        return $agents;
    }

    public static function statusLabel(?string $status): string
    {
        return self::STATUSES[$status]['label'] ?? 'Not Reviewed';
    }

    public static function statusColor(?string $status): string
    {
        return self::STATUSES[$status]['color'] ?? '#64748b';
    }

    public static function isUnreviewed(?string $status): bool
    {
        return $status === null || $status === self::UNREVIEWED;
    }

    public static function statusKeys(): array
    {
        return array_keys(self::STATUSES);
    }

    public static function typeLabel(?string $type): string
    {
        return self::TYPES[$type]['label'] ?? ucfirst((string) $type);
    }

    public static function typeColor(?string $type): string
    {
        return self::TYPES[$type]['color'] ?? '#64748b';
    }

    public static function priorityColor(?string $priority): string
    {
        return self::PRIORITIES[$priority]['color'] ?? '#64748b';
    }

    public static function nextReference(): string
    {
        $last = static::orderByDesc('id')->value('id') ?? 0;

        return 'RPT-' . str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Statuses that mean the work is finished.
     */
    public static function closingStatuses(): array
    {
        return ['verified', 'closed', 'risk_accepted', 'false_positive'];
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', array_keys(array_filter(
            self::STATUSES,
            fn ($s) => $s['open']
        )));
    }
}
