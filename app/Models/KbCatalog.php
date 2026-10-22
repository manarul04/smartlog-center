<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbCatalog extends Model
{
    protected $table = 'kb_catalog';

    protected $fillable = [
        'kb', 'title', 'product', 'classification', 'variants',
        'source', 'status', 'attempts', 'fetched_at',
    ];

    protected $casts = [
        'variants' => 'array',
        'fetched_at' => 'datetime',
    ];

    /**
     * Cached titles for the given article numbers, keyed by KB.
     *
     * Rows that were looked up but not found in the catalog are included with
     * a null title so the page can say so instead of showing a bare code.
     */
    public static function forKeys(array $kbs): array
    {
        $kbs = array_values(array_unique(array_filter($kbs)));

        if ($kbs === []) {
            return [];
        }

        return self::query()
            ->whereIn('kb', $kbs)
            ->get()
            ->keyBy('kb')
            ->all();
    }
}
