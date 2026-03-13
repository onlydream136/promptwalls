<?php

namespace App\Services;

use App\Models\SystemConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssessorService
{
    /**
     * Assess whether the text contains sensitive/PII information.
     * Returns structured assessment result.
     */
    public function assess(string $text): array
    {
        $endpoint = SystemConfig::get('assessment_endpoint', '127.0.0.1:11434');
        $model = SystemConfig::get('assessment_model', 'qwen3:14b');

        $prompt = <<<PROMPT
你是一个隐私内容审查专家。请分析以下文本，判断是否包含敏感个人信息(PII)。

需要检测的敏感信息类型：
1. 姓名 (name)
2. 身份证号 (id_number)
3. 手机号/电话 (phone)
4. 地址 (address)
5. 出生日期 (date_of_birth)
6. 银行卡号 (bank_card)
7. 邮箱 (email)
8. 护照号 (passport)
9. 社保号 (social_security)
10. 医疗信息 (medical)
11. 公司机密 (confidential)

请以XML格式返回结果：
<assessment>
  <has_sensitive>true或false</has_sensitive>
  <risk_level>none|low|medium|high|critical</risk_level>
  <entities>
    <entity>
      <type>类型英文标识</type>
      <value>检测到的值</value>
      <position>大致位置描述</position>
    </entity>
  </entities>
  <summary>简要说明</summary>
</assessment>

待检测文本：
---
{$text}
---

请只返回XML，不要添加其他说明。
PROMPT;

        try {
            $baseUrl = str_starts_with($endpoint, 'http') ? $endpoint : "http://{$endpoint}";
            $response = Http::timeout(180)->post("{$baseUrl}/api/generate", [
                'model' => $model,
                'prompt' => $prompt,
                'stream' => false,
            ]);

            if ($response->successful()) {
                $raw = $response->json('response', '');

                // Extract XML from response
                if (preg_match('/<assessment>[\s\S]*?<\/assessment>/', $raw, $matches)) {
                    $parsed = $this->parseXmlAssessment($matches[0]);
                    if (!empty($parsed)) {
                        return $parsed;
                    }
                }

                Log::warning('Assessment returned no valid XML', ['raw' => $raw]);
            }

            Log::error('Assessment API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::error('Assessment service exception', ['message' => $e->getMessage()]);
        }

        // Fail-safe: treat assessment failure as error, not as "safe"
        throw new \RuntimeException('Assessment service unavailable or returned invalid response');
    }

    /**
     * Parse the XML assessment response into an array.
     */
    private function parseXmlAssessment(string $xml): array
    {
        try {
            $doc = new \SimpleXMLElement($xml);

            $hasSensitive = strtolower(trim((string) $doc->has_sensitive)) === 'true';
            $riskLevel = trim((string) $doc->risk_level) ?: 'none';
            $summary = trim((string) $doc->summary) ?: '';

            $entities = [];
            if (isset($doc->entities->entity)) {
                foreach ($doc->entities->entity as $entity) {
                    $entities[] = [
                        'type' => trim((string) $entity->type),
                        'value' => trim((string) $entity->value),
                        'position' => trim((string) $entity->position),
                    ];
                }
            }

            return [
                'has_sensitive' => $hasSensitive,
                'risk_level' => $riskLevel,
                'entities' => $entities,
                'summary' => $summary,
            ];
        } catch (\Exception $e) {
            Log::error('XML parse failed', ['error' => $e->getMessage(), 'xml' => $xml]);
            return [];
        }
    }

    /**
     * Test connection to the assessment endpoint.
     */
    public function testConnection(): array
    {
        $endpoint = SystemConfig::get('assessment_endpoint', '127.0.0.1:11434');

        try {
            $baseUrl = str_starts_with($endpoint, 'http') ? $endpoint : "http://{$endpoint}";
            $response = Http::timeout(10)->get("{$baseUrl}/api/tags");

            if ($response->successful()) {
                $models = collect($response->json('models', []))
                    ->pluck('name')
                    ->toArray();

                return [
                    'success' => true,
                    'message' => 'Connected successfully',
                    'models' => $models,
                ];
            }

            return [
                'success' => false,
                'message' => "Connection failed: HTTP {$response->status()}",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "Connection failed: {$e->getMessage()}",
            ];
        }
    }
}
