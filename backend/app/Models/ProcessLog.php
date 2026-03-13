<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'file_record_id', 'action', 'details',
    ];

    public function fileRecord(): BelongsTo
    {
        return $this->belongsTo(FileRecord::class);
    }
}
