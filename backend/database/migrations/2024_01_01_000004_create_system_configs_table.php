<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_configs', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Insert default configs
        DB::table('system_configs')->insert([
            ['key' => 'ocr_endpoint', 'value' => '127.0.0.1:11434', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'ocr_model', 'value' => 'glm-ocr-lastest', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'assessment_endpoint', 'value' => '127.0.0.1:11434', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'assessment_model', 'value' => 'qwen3:14b', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'income_files_path', 'value' => 'C:\\IncomeFiles', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'no_sensitive_path', 'value' => 'C:\\NoSentiveInfo', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'sensitive_files_path', 'value' => 'C:\\SentiveFiles', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'desensitized_files_path', 'value' => 'C:\\DesentizedFiles', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'use_llm_desensitize', 'value' => 'true', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_configs');
    }
};
