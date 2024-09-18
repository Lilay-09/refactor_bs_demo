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
        Schema::table('daily_stocks', function (Blueprint $table) {
            //
            $table->bigInteger('missing_qty')->default(0);
            $table->bigInteger('return_qty')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_stocks', function (Blueprint $table) {
            //
        });
    }
};
