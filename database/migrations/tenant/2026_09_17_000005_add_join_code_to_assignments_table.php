<?php

use App\Models\Assignment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('assignments', 'join_code')) {
                $table->string('join_code', 8)->nullable()->unique();
            }
        });

        Assignment::query()
            ->whereNull('demo_id')
            ->whereNull('join_code')
            ->each(function (Assignment $assignment): void {
                $assignment->update([
                    'join_code' => Assignment::generateJoinCode(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasColumn('assignments', 'join_code')) {
                $table->dropUnique(['join_code']);
                $table->dropColumn('join_code');
            }
        });
    }
};
