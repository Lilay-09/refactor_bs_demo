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
        Schema::table('companies', function (Blueprint $table) {
            //
            $table->string('photo_file_name',200)->nullable();
            $table->string('address_kh',250)->nullable();
            $table->string('remarks',300)->nullable();
            $table->string('cp_phone',30)->nullable();
            $table->string('cp_email',100)->nullable();
            $table->boolean('inactive')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            //
        });
    }
};
