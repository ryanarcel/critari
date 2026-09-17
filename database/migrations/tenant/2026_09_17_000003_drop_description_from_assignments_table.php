<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasColumn('assignments', 'description')) {
                $table->dropColumn('description');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('assignments', 'description')) {
            Schema::table('assignments', function (Blueprint $table) {
                $table->text('description')->nullable()->after('title');
            });
        }

        $assignments = DB::table('assignments')->select('id')->get();

        foreach ($assignments as $assignment) {
            $prompts = DB::table('questions')
                ->where('assignment_id', $assignment->id)
                ->orderBy('order')
                ->pluck('prompt')
                ->filter()
                ->values();

            $description = $prompts->count() <= 1
                ? (string) $prompts->first()
                : $prompts
                    ->map(fn (string $prompt, int $index): string => ($index + 1).'. '.$prompt)
                    ->implode("\n\n");

            DB::table('assignments')->where('id', $assignment->id)->update([
                'description' => $description !== '' ? $description : null,
            ]);
        }
    }
};
