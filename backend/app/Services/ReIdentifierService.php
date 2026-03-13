<?php

namespace App\Services;

use App\Models\WordPair;

class ReIdentifierService
{
    /**
     * Re-identify text by replacing placeholders with original values.
     * Searches across all word pairs or within a specific file's pairs.
     */
    public function reidentify(string $text, ?int $fileRecordId = null): array
    {
        $query = WordPair::query();

        if ($fileRecordId) {
            $query->where('file_record_id', $fileRecordId);
        }

        $pairs = $query->get();

        $replacements = [];

        foreach ($pairs as $pair) {
            if (str_contains($text, $pair->placeholder)) {
                $text = str_replace($pair->placeholder, $pair->original_value, $text);
                $replacements[] = [
                    'placeholder' => $pair->placeholder,
                    'original' => $pair->original_value,
                    'type' => $pair->entity_type,
                ];
            }
        }

        return [
            'restored_text' => $text,
            'replacements_made' => count($replacements),
            'replacements' => $replacements,
        ];
    }

    /**
     * Auto-detect which file record's word pairs match the text.
     */
    public function detectFileRecord(string $text): ?int
    {
        // Find all unique placeholders in the text
        preg_match_all('/\[\[[A-Z_]+\d{3}\]\]/', $text, $matches);

        if (empty($matches[0])) {
            return null;
        }

        $placeholders = array_unique($matches[0]);

        // Find which file_record_id has the most matching pairs
        $counts = WordPair::whereIn('placeholder', $placeholders)
            ->selectRaw('file_record_id, COUNT(*) as match_count')
            ->groupBy('file_record_id')
            ->orderByDesc('match_count')
            ->first();

        return $counts?->file_record_id;
    }
}
