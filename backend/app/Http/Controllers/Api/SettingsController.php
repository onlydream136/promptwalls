<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SystemConfig;
use App\Services\AssessorService;
use App\Services\OcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        // Default values for all settings
        $defaults = [
            'ocr_endpoint' => '127.0.0.1:11434',
            'ocr_model' => '',
            'assessment_endpoint' => '127.0.0.1:11434',
            'assessment_model' => 'qwen3:14b',
            'income_files_path' => '',
            'no_sensitive_path' => '',
            'sensitive_files_path' => '',
            'desensitized_files_path' => '',
            'use_llm_desensitize' => 'true',
        ];

        $configs = SystemConfig::all()->pluck('value', 'key')->toArray();

        return response()->json(array_merge($defaults, $configs));
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        foreach ($request->input('settings') as $key => $value) {
            SystemConfig::set($key, $value);
        }

        return response()->json(['message' => 'Settings updated successfully']);
    }

    public function testConnection(Request $request, OcrService $ocr, AssessorService $assessor): JsonResponse
    {
        $type = $request->input('type', 'ocr');

        $result = match ($type) {
            'ocr' => $ocr->testConnection(),
            'assessment' => $assessor->testConnection(),
            default => ['success' => false, 'message' => 'Unknown connection type'],
        };

        return response()->json($result);
    }
}
