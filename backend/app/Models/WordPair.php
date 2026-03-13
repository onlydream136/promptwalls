<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WordPair extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'file_record_id', 'placeholder', 'original_value', 'entity_type',
    ];

    public function fileRecord(): BelongsTo
    {
        return $this->belongsTo(FileRecord::class);
    }
}
