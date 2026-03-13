<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FileRecord extends Model
{
    protected $fillable = [
        'filename', 'file_type', 'file_size', 'status', 'folder',
        'source_path', 'output_path', 'extracted_text', 'assessment_result',
    ];

    protected $casts = [
        'assessment_result' => 'array',
        'file_size' => 'integer',
    ];

    public function wordPairs(): HasMany
    {
        return $this->hasMany(WordPair::class);
    }

    public function processLogs(): HasMany
    {
        return $this->hasMany(ProcessLog::class);
    }
}
