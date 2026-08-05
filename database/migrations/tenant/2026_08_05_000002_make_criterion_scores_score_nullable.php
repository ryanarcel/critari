<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeCriterionScoresScoreNullable extends Migration
{
    public function up()
    {
        Schema::table('criterion_scores', function (Blueprint $table) {
            $table->decimal('score', 8, 3)->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('criterion_scores', function (Blueprint $table) {
            $table->decimal('score', 8, 3)->change();
        });
    }
}
