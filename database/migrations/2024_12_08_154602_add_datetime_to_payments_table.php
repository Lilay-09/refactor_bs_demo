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
        Schema::table('payments', function (Blueprint $table) {
            //
            $table->dateTime('approved_datetime')->nullable();
            $table->dateTime('settled_datetime')->nullable();
            $table->dateTime('received_datetime')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            //
            $table->dropColumn([
                'received_datetime',
                'settled_datetime',
                'approved_datetime',
            ]);
        });
    }
};
