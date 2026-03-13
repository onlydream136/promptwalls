<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$endpoint = 'http://voogpt.com:9004';
$model = 'qwen3:14b';

// Test 1: Simple sensitive text
$prompt1 = '你是一个隐私内容审查专家。请分析以下文本是否包含敏感个人信息，以JSON格式返回 {"has_sensitive": true/false, "entities": [{"type":"类型","value":"值"}], "summary":"说明"}。

文本：张三的身份证号是110101199001011234，手机号13800138000，邮箱zhangsan@qq.com。

请只返回JSON。';

echo "=== Test 1: 明确敏感文本 ===" . PHP_EOL;
$r1 = Illuminate\Support\Facades\Http::timeout(180)->post("{$endpoint}/api/generate", [
    'model' => $model,
    'prompt' => $prompt1,
    'stream' => false,
    'format' => 'json',
]);
echo "Status: " . $r1->status() . PHP_EOL;
echo "Response: " . $r1->json('response', '(empty)') . PHP_EOL . PHP_EOL;

// Test 2: Simple question - no format constraint
$prompt2 = '以下文本包含哪些个人隐私信息？请列出来。

文本：我叫李四，住在北京市朝阳区建国路88号，银行卡号6222021234567890123。';

echo "=== Test 2: 不要求JSON格式 ===" . PHP_EOL;
$r2 = Illuminate\Support\Facades\Http::timeout(180)->post("{$endpoint}/api/generate", [
    'model' => $model,
    'prompt' => $prompt2,
    'stream' => false,
]);
echo "Status: " . $r2->status() . PHP_EOL;
echo "Response: " . $r2->json('response', '(empty)') . PHP_EOL . PHP_EOL;

// Test 3: Very simple test
$prompt3 = '1+1等于几？';

echo "=== Test 3: 简单数学题 ===" . PHP_EOL;
$r3 = Illuminate\Support\Facades\Http::timeout(180)->post("{$endpoint}/api/generate", [
    'model' => $model,
    'prompt' => $prompt3,
    'stream' => false,
]);
echo "Status: " . $r3->status() . PHP_EOL;
echo "Response: " . $r3->json('response', '(empty)') . PHP_EOL;
