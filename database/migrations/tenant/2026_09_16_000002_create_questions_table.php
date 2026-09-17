<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assignment_id')->index();
            $table->text('prompt');
            $table->integer('order')->default(0)->index();
            $table->timestamps();
        });

        if (Schema::hasColumn('assignments', 'questions')) {
            $assignments = DB::table('assignments')->select('id', 'questions')->get();

            foreach ($assignments as $assignment) {
                $raw = $assignment->questions;
                $questions = is_array($raw) ? $raw : json_decode($raw ?? '[]', true);

                if (! is_array($questions)) {
                    continue;
                }

                foreach ($questions as $index => $question) {
                    $prompt = is_string($question)
                        ? trim($question)
                        : trim((string) (is_array($question) ? ($question['prompt'] ?? '') : ''));

                    if ($prompt === '') {
                        continue;
                    }

                    DB::table('questions')->insert([
                        'assignment_id' => $assignment->id,
                        'prompt' => $prompt,
                        'order' => $index,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            Schema::table('assignments', function (Blueprint $table) {
                $table->dropColumn('questions');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('assignments', 'questions')) {
            Schema::table('assignments', function (Blueprint $table) {
                $table->json('questions')->nullable()->after('description');
            });

            $grouped = DB::table('questions')->orderBy('order')->get()->groupBy('assignment_id');

            foreach ($grouped as $assignmentId => $questions) {
                DB::table('assignments')->where('id', $assignmentId)->update([
                    'questions' => json_encode(
                        $questions->map(fn ($question) => ['prompt' => $question->prompt])->values()->all()
                    ),
                ]);
            }
        }

        Schema::dropIfExists('questions');
    }
};
