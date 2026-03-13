<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$text = "我是一个测试文本1684564546@qq.com\n下面在用两个邮箱测试一个google邮箱qshxiaochen@gmail.com多加点文字\n第二个用qq邮箱only_dream136@sina.com\n我有一个qq邮箱1577171300@qq.com，先发这一个试试 431222199602233658这是我的身份证";

$endpoint = App\Models\SystemConfig::get('assessment_endpoint', '127.0.0.1:11434');
$model = App\Models\SystemConfig::get('assessment_model', 'qwen3:14b');

echo "Endpoint: {$endpoint}" . PHP_EOL;
echo "Model: {$model}" . PHP_EOL . PHP_EOL;

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

请以JSON格式返回结果：
{
  "has_sensitive": true/false,
  "risk_level": "none|low|medium|high|critical",
  "entities": [
    {"type": "类型", "value": "检测到的值", "position": "大致位置描述"}
  ],
  "summary": "简要说明"
}

待检测文本：
---
{$text}
---

请只返回JSON，不要添加其他说明。
PROMPT;

$baseUrl = str_starts_with($endpoint, 'http') ? $endpoint : "http://{$endpoint}";

echo "=== 发送请求到: {$baseUrl}/api/generate ===" . PHP_EOL;

$response = Illuminate\Support\Facades\Http::timeout(180)->post("{$baseUrl}/api/generate", [
    'model' => $model,
    'prompt' => $prompt,
    'stream' => false,
    'format' => 'json',
]);

echo "HTTP Status: " . $response->status() . PHP_EOL;
echo "=== 原始响应 ===" . PHP_EOL;
$raw = $response->json('response', '(empty)');
echo $raw . PHP_EOL;
echo "=== 解析结果 ===" . PHP_EOL;
$parsed = json_decode($raw, true);
echo "JSON parse error: " . json_last_error_msg() . PHP_EOL;
echo json_encode($parsed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
