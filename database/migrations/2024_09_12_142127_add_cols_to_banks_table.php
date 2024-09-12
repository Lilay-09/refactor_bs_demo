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
        Schema::table('banks', function (Blueprint $table) {
            //
            $table->string('name_kh',50)->nullable();
            $table->string('photo_file_name',200)->nullable();
            $table->string('photo_file_name_kh',200)->nullable();
            $table->boolean('inactive')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banks', function (Blueprint $table) {
            //
        });
    }
};
