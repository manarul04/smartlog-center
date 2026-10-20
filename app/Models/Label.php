<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Label extends Model
{
    protected $fillable = ['name', 'color', 'description', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];

    public function annotations(): BelongsToMany
    {
        return $this->belongsToMany(AlertAnnotation::class, 'alert_annotation_label');
    }
}
