<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('criterion_scores', function (Blueprint $table) {
            if (! Schema::hasColumn('criterion_scores', 'question_id')) {
                $table->unsignedBigInteger('question_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('criterion_scores', function (Blueprint $table) {
            if (Schema::hasColumn('criterion_scores', 'question_id')) {
                $table->dropColumn('question_id');
            }
        });
    }
};
