<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSessionIdToDemosTable extends Migration
{
    public function up()
    {
        Schema::table('demos', function (Blueprint $table) {
            $table->string('session_id')->nullable()->unique()->after('title');
        });
    }

    public function down()
    {
        Schema::table('demos', function (Blueprint $table) {
            $table->dropColumn('session_id');
        });
    }
}
