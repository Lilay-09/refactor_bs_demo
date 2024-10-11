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
        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->dateTime('failed_datetime')->nullable();
            $table->string('failure_notes',500)->nullable();
            $table->string('receiver_address',500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->dropColumn([
                'failure_notes',
                'receiver_address',
                'failed_datetime'
            ]);
        });
    }
};
