<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'target', 'ip', 'user_agent', 'meta'];

    protected $casts = ['meta' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?string $target = null, array $meta = []): self
    {
        $request = request();

        return self::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'target' => $target,
            'ip' => $request?->ip(),
            'user_agent' => mb_substr((string) $request?->userAgent(), 0, 255),
            'meta' => $meta ?: null,
        ]);
    }
}
