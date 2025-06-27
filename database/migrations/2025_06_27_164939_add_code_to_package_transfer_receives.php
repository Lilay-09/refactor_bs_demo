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
        Schema::table('package_transfer_receives', function (Blueprint $table) {
            //
            $table->string('code',50)->nullable();
            $table->string('receive_datetime')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_transfer_receives', function (Blueprint $table) {
            //
            $table->dropColumn([
                'code',
                'receive_datetime'
            ]);
        });
    }
};
