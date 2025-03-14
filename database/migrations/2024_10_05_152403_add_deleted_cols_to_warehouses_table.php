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
        Schema::table('warehouses', function (Blueprint $table) {
            //
            $table->dateTime('deleted_datetime')->nullable();
            $table->unsignedBigInteger('deleted_uid')->nullable();
            $table->boolean('is_deleted')->default(false);

            $table->foreign('deleted_uid')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            //
            $table->dropForeign(['deleted_uid']);

            // Drop the columns
            $table->dropColumn(['deleted_datetime', 'deleted_uid', 'is_deleted']);
        });
    }
};
