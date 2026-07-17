<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('demos', function (Blueprint $table) {
            $table->bigIncrements('id');

            // tenancy: optional tenant reference if using separate tenant database or central lookup
            $table->string('tenant_id')->nullable()->index();

            $table->foreignId('assignment_id')->nullable();

            $table->string('title')->nullable();
            $table->text('body')->nullable();

            $table->timestamp('submitted_at')->nullable()->index();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demos');
    }
};
