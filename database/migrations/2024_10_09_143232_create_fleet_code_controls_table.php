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
        Schema::create('fleet_code_controls', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id');
            $table->string('prefix',5)->nullable();
            $table->string('last_idx',5)->nullable();
            $table->string('year',5)->nullable();
            $table->string('month',3)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fleet_code_controls');
    }
};
