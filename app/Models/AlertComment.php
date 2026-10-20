<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertComment extends Model
{
    protected $fillable = ['alert_annotation_id', 'user_id', 'body'];

    public function annotation(): BelongsTo
    {
        return $this->belongsTo(AlertAnnotation::class, 'alert_annotation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
