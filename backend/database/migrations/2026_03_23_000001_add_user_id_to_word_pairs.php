<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('word_pairs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('file_record_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('word_pairs', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
