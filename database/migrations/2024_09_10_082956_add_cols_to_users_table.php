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
        Schema::table('users', function (Blueprint $table) {
            //
            $table->dateTime('start_date')->nullable();
            $table->dateTime('last_login')->default(now());
            $table->string('description',250)->nullable();
            $table->string('user_class',200)->default('admin');
            $table->unsignedBigInteger('official_id')->nullable();
            $table->string('photo_file_name',200)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
