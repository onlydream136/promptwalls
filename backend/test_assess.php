<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$converter = app(App\Services\FileConverterService::class);
$assessor = app(App\Services\AssessorService::class);

$path = '/var/www/html/storage/app/test_email.docx';

echo "=== Step 1: 文本提取 ===" . PHP_EOL;
$text = $converter->convertToText($path);
echo $text . PHP_EOL;
echo PHP_EOL;

echo "=== Step 2: 调用 qwen3:14b 评估 ===" . PHP_EOL;
try {
    $result = $assessor->assess($text);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
echo "=== 完毕 ===" . PHP_EOL;
