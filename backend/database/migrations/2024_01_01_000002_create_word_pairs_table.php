<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_record_id')->constrained('file_records')->cascadeOnDelete();
            $table->string('placeholder', 100);
            $table->string('original_value', 500);
            $table->string('entity_type', 50)->default('unknown');
            $table->timestamp('created_at')->useCurrent();

            $table->index('file_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('word_pairs');
    }
};
