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
            $table->dateTime('registered_datetime')->nullable();
            $table->string('app_id')->nullable();
        });
        Schema::table('users', function (Blueprint $table) {
            DB::table('users')
            ->whereNull('registered_datetime')
            ->update(['registered_datetime' => DB::raw('created_at')]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
            $table->dropColumn(['registered_datetime','app_id']);
        });
    }
};
