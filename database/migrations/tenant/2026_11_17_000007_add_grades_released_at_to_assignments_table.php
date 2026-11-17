<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('assignments', 'grades_released_at')) {
                $table->timestamp('grades_released_at')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasColumn('assignments', 'grades_released_at')) {
                $table->dropColumn('grades_released_at');
            }
        });
    }
};
