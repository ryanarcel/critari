<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('o_auth_states', function (Blueprint $table) {
            if (! Schema::hasColumn('o_auth_states', 'intended_role')) {
                $table->string('intended_role')->nullable()->after('tenant_host');
            }
        });
    }

    public function down(): void
    {
        Schema::table('o_auth_states', function (Blueprint $table) {
            if (Schema::hasColumn('o_auth_states', 'intended_role')) {
                $table->dropColumn('intended_role');
            }
        });
    }
};
