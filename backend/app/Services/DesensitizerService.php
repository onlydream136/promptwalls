<?php

namespace App\Services;

use App\Models\FileRecord;
use App\Models\SystemConfig;
use App\Models\WordPair;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DesensitizerService
{
    private array $entityCounters = [];

    /**
     * Desensitize text by replacing sensitive entities with placeholders.
     * Returns the desensitized text and creates word pair records.
     */
    public function desensitize(FileRecord $fileRecord, string $text, array $assessmentResult): string
    {
        // Pre-load counters from existing user word pairs to avoid placeholder conflicts
        $this->initCountersFromExistingPairs($fileRecord->user_id);

        $useLlm = SystemConfig::get('use_llm_desensitize', 'true') === 'true';

        if ($useLlm && !empty($assessmentResult['entities'])) {
            return $this->desensitizeWithLlm($fileRecord, $text, $assessmentResult);
        }

        return $this->desensitizeWithRegex($fileRecord, $text, $assessmentResult);
    }

    /**
     * Initialize counters from existing word pairs to avoid placeholder conflicts.
     */
    private function initCountersFromExistingPairs(?int $userId): void
    {
        $this->entityCounters = [];

        if (!$userId) return;

        $existingPairs = WordPair::where('user_id', $userId)->pluck('placeholder');

        foreach ($existingPairs as $placeholder) {
            // Parse [[PREFIX_NNN]] format
            if (preg_match('/^\[\[([A-Z]+)_(\d+)\]\]$/', $placeholder, $matches)) {
                $prefix = $matches[1];
                $num = (int) $matches[2];
                if (!isset($this->entityCounters[$prefix]) || $num > $this->entityCounters[$prefix]) {
                    $this->entityCounters[$prefix] = $num;
                }
            }
        }
    }

    /**
     * Find existing word pair for this user with the same original value,
     * or create a new one.
     */
    private function findOrCreatePair(FileRecord $fileRecord, string $originalValue, string $entityType): string
    {
        // Check if this user already has a word pair for this value
        if ($fileRecord->user_id) {
            $existing = WordPair::where('user_id', $fileRecord->user_id)
                ->where('original_value', $originalValue)
                ->first();

            if ($existing) {
                return $existing->placeholder;
            }
        }

        // Create new pair
        $placeholder = $this->generatePlaceholder($entityType);

        WordPair::create([
            'file_record_id' => $fileRecord->id,
            'user_id' => $fileRecord->user_id,
            'placeholder' => $placeholder,
            'original_value' => $originalValue,
            'entity_type' => $entityType,
        ]);

        return $placeholder;
    }

    /**
     * Use LLM to perform intelligent desensitization.
     */
    private function desensitizeWithLlm(FileRecord $fileRecord, string $text, array $assessmentResult): string
    {
        $endpoint = SystemConfig::get('assessment_endpoint', '127.0.0.1:11434');
        $model = SystemConfig::get('assessment_model', 'qwen3:14b');

        $entitiesJson = json_encode($assessmentResult['entities'], JSON_UNESCAPED_UNICODE);

        $prompt = <<<PROMPT
你是一个数据脱敏专家。请将以下文本中的敏感信息替换为占位符。

已检测到的敏感实体：
{$entitiesJson}

替换规则：
- 姓名 → [[NAME_001]], [[NAME_002]]...
- 身份证号 → [[ID_001]], [[ID_002]]...
- 手机号 → [[PHONE_001]]...
- 地址 → [[ADDR_001]]...
- 日期 → [[DATE_001]]...
- 银行卡号 → [[BANK_001]]...
- 邮箱 → [[EMAIL_001]]...
- 护照号 → [[PASSPORT_001]]...
- 其他敏感信息 → [[ENTITY_001]]...

请以JSON格式返回：
{
  "desensitized_text": "脱敏后的完整文本",
  "replacements": [
    {"placeholder": "[[NAME_001]]", "original": "原始值", "type": "name"}
  ]
}

原始文本：
---
{$text}
---

请只返回JSON。
PROMPT;

        try {
            $baseUrl = str_starts_with($endpoint, 'http') ? $endpoint : "http://{$endpoint}";
            $response = Http::timeout(180)->post("{$baseUrl}/api/generate", [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
                'format' => 'json',
            ]);

            if ($response->successful()) {
                $result = json_decode($response->json('response', '{}'), true);

                if (!empty($result['desensitized_text']) && !empty($result['replacements'])) {
                    // Save word pairs, reusing existing ones
                    $finalText = $result['desensitized_text'];

                    foreach ($result['replacements'] as $replacement) {
                        $original = $replacement['original'];
                        $type = $replacement['type'] ?? 'unknown';
                        $llmPlaceholder = $replacement['placeholder'];

                        $actualPlaceholder = $this->findOrCreatePair($fileRecord, $original, $type);

                        // Replace LLM's placeholder with our consistent one
                        if ($llmPlaceholder !== $actualPlaceholder) {
                            $finalText = str_replace($llmPlaceholder, $actualPlaceholder, $finalText);
                        }
                    }

                    return $finalText;
                }
            }
        } catch (\Exception $e) {
            Log::error('LLM desensitization failed', ['message' => $e->getMessage()]);
        }

        // Fallback to regex-based desensitization
        return $this->desensitizeWithRegex($fileRecord, $text, $assessmentResult);
    }

    /**
     * Regex-based desensitization as fallback.
     */
    private function desensitizeWithRegex(FileRecord $fileRecord, string $text, array $assessmentResult): string
    {
        if (!empty($assessmentResult['entities'])) {
            foreach ($assessmentResult['entities'] as $entity) {
                $value = $entity['value'] ?? '';
                $type = $entity['type'] ?? 'unknown';

                if (empty($value) || mb_strlen($value) < 2) {
                    continue;
                }

                $placeholder = $this->findOrCreatePair($fileRecord, $value, $type);

                // Escape special regex characters
                $escaped = preg_quote($value, '/');
                $text = preg_replace("/{$escaped}/u", $placeholder, $text);
            }
        }

        // Additional regex patterns for common PII
        $patterns = [
            'phone' => '/(?<!\d)1[3-9]\d{9}(?!\d)/',
            'id_number' => '/(?<!\d)\d{17}[\dXx](?!\d)/',
            'email' => '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/',
            'bank_card' => '/(?<!\d)\d{16,19}(?!\d)/',
        ];

        foreach ($patterns as $type => $pattern) {
            $text = preg_replace_callback($pattern, function ($matches) use ($fileRecord, $type) {
                $value = $matches[0];
                if (str_starts_with($value, '[[')) {
                    return $value;
                }

                return $this->findOrCreatePair($fileRecord, $value, $type);
            }, $text);
        }

        return $text;
    }

    private function generatePlaceholder(string $type): string
    {
        $prefixMap = [
            'name' => 'NAME',
            'id_number' => 'ID',
            'phone' => 'PHONE',
            'address' => 'ADDR',
            'date_of_birth' => 'DATE',
            'date' => 'DATE',
            'bank_card' => 'BANK',
            'email' => 'EMAIL',
            'passport' => 'PASSPORT',
            'social_security' => 'SSN',
            'medical' => 'MEDICAL',
            'confidential' => 'CONF',
        ];

        $prefix = $prefixMap[$type] ?? 'ENTITY';

        if (!isset($this->entityCounters[$prefix])) {
            $this->entityCounters[$prefix] = 0;
        }

        $this->entityCounters[$prefix]++;
        $num = str_pad($this->entityCounters[$prefix], 3, '0', STR_PAD_LEFT);

        return "[[{$prefix}_{$num}]]";
    }
}
