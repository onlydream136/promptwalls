<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_records', function (Blueprint $table) {
            $table->id();
            $table->string('filename', 255);
            $table->string('file_type', 20);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->enum('status', [
                'pending', 'ocr_scanning', 'assessing',
                'sensitive', 'no_risk', 'desensitized'
            ])->default('pending');
            $table->enum('folder', [
                'incoming', 'clean', 'sensitive', 'desensitized'
            ])->default('incoming');
            $table->string('source_path', 500);
            $table->string('output_path', 500)->nullable();
            $table->longText('extracted_text')->nullable();
            $table->json('assessment_result')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('folder');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_records');
    }
};
