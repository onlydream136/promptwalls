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
        $useLlm = SystemConfig::get('use_llm_desensitize', 'true') === 'true';

        if ($useLlm && !empty($assessmentResult['entities'])) {
            return $this->desensitizeWithLlm($fileRecord, $text, $assessmentResult);
        }

        return $this->desensitizeWithRegex($fileRecord, $text, $assessmentResult);
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
                    // Save word pairs to database
                    foreach ($result['replacements'] as $replacement) {
                        WordPair::create([
                            'file_record_id' => $fileRecord->id,
                            'placeholder' => $replacement['placeholder'],
                            'original_value' => $replacement['original'],
                            'entity_type' => $replacement['type'] ?? 'unknown',
                        ]);
                    }

                    return $result['desensitized_text'];
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
        $this->entityCounters = [];

        if (!empty($assessmentResult['entities'])) {
            foreach ($assessmentResult['entities'] as $entity) {
                $value = $entity['value'] ?? '';
                $type = $entity['type'] ?? 'unknown';

                if (empty($value) || mb_strlen($value) < 2) {
                    continue;
                }

                $placeholder = $this->generatePlaceholder($type);

                // Escape special regex characters
                $escaped = preg_quote($value, '/');
                $text = preg_replace("/{$escaped}/u", $placeholder, $text);

                WordPair::create([
                    'file_record_id' => $fileRecord->id,
                    'placeholder' => $placeholder,
                    'original_value' => $value,
                    'entity_type' => $type,
                ]);
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
                // Check if already replaced
                if (str_starts_with($value, '[[')) {
                    return $value;
                }

                $placeholder = $this->generatePlaceholder($type);

                WordPair::create([
                    'file_record_id' => $fileRecord->id,
                    'placeholder' => $placeholder,
                    'original_value' => $value,
                    'entity_type' => $type,
                ]);

                return $placeholder;
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
