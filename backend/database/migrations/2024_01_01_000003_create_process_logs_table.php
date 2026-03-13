<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_record_id')->constrained('file_records')->cascadeOnDelete();
            $table->string('action', 50);
            $table->text('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('file_record_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_logs');
    }
};
