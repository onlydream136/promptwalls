<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add retry_count column
        Schema::table('file_records', function (Blueprint $table) {
            $table->unsignedTinyInteger('retry_count')->default(0)->after('assessment_result');
        });

        // Update status enum to include 'failed'
        DB::statement("ALTER TABLE file_records MODIFY COLUMN status ENUM('pending','ocr_scanning','assessing','sensitive','no_risk','desensitized','failed') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE file_records MODIFY COLUMN status ENUM('pending','ocr_scanning','assessing','sensitive','no_risk','desensitized') DEFAULT 'pending'");

        Schema::table('file_records', function (Blueprint $table) {
            $table->dropColumn('retry_count');
        });
    }
};
