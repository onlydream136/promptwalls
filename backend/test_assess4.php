<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$endpoint = 'http://voogpt.com:9004';
$model = 'qwen3:14b';

$prompt = '你是一个隐私内容审查专家。请分析以下文本，判断是否包含敏感个人信息(PII)。

需要检测的敏感信息类型：姓名、身份证号、手机号、地址、邮箱、银行卡号等。

请以XML格式返回结果：
<assessment>
  <has_sensitive>true或false</has_sensitive>
  <risk_level>none|low|medium|high|critical</risk_level>
  <entities>
    <entity>
      <type>类型</type>
      <value>检测到的值</value>
    </entity>
  </entities>
  <summary>简要说明</summary>
</assessment>

待检测文本：
---
张三的身份证号是110101199001011234，手机号13800138000，邮箱zhangsan@qq.com，住在北京市朝阳区建国路88号。
---

请只返回XML，不要添加其他说明。';

echo "=== Test: XML格式输出 ===" . PHP_EOL;
$r = Illuminate\Support\Facades\Http::timeout(180)->post("{$endpoint}/api/generate", [
    'model' => $model,
    'prompt' => $prompt,
    'stream' => false,
]);
echo "Status: " . $r->status() . PHP_EOL;
echo "Response:" . PHP_EOL;
echo $r->json('response', '(empty)') . PHP_EOL;
