<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMaxScoreAndLevelsToAssignmentsTable extends Migration
{
    public function up()
    {
        Schema::table('assignments', function (Blueprint $table) {
            // Only add max_score if it doesn't already exist
            if (!Schema::hasColumn('assignments', 'max_score')) {
                $table->unsignedInteger('max_score')->nullable()->after('description')->index();
            }
        });
    }

    public function down()
    {
        Schema::table('assignments', function (Blueprint $table) {
            if (Schema::hasColumn('assignments', 'max_score')) {
                $table->dropColumn('max_score');
            }
        });
    }
}
