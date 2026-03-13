<?php

namespace App\Services;

use App\Models\SystemConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OcrService
{
    /**
     * Perform OCR on an image file using Ollama API.
     */
    public function recognize(string $imagePath): string
    {
        $endpoint = SystemConfig::get('ocr_endpoint', '127.0.0.1:11434');
        $model = SystemConfig::get('ocr_model', 'glm-ocr-lastest');

        $imageData = base64_encode(file_get_contents($imagePath));

        try {
            $baseUrl = str_starts_with($endpoint, 'http') ? $endpoint : "http://{$endpoint}";
            $response = Http::timeout(120)->post("{$baseUrl}/api/generate", [
                'model' => $model,
                'prompt' => '请识别并提取图片中的所有文字内容，保持原始格式。只输出识别到的文字，不要添加额外说明。',
                'images' => [$imageData],
                'stream' => false,
            ]);

            if ($response->successful()) {
                return $response->json('response', '');
            }

            Log::error('OCR API error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return '';
        } catch (\Exception $e) {
            Log::error('OCR service exception', ['message' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * Test connection to the OCR endpoint.
     */
    public function testConnection(): array
    {
        $endpoint = SystemConfig::get('ocr_endpoint', '127.0.0.1:11434');

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
