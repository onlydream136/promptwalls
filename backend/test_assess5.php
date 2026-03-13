<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$assessor = app(App\Services\AssessorService::class);

$text = "我是一个测试文本1684564546@qq.com\n下面在用两个邮箱测试一个google邮箱qshxiaochen@gmail.com多加点文字\n第二个用qq邮箱only_dream136@sina.com\n我有一个qq邮箱1577171300@qq.com，先发这一个试试 431222199602233658这是我的身份证";

echo "=== 用新的XML方案测试 email.docx 内容 ===" . PHP_EOL;
try {
    $result = $assessor->assess($text);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
