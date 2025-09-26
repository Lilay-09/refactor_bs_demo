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
        Schema::table('telegram_bot_users', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('user_id');
            $table->string('type')->default('merchant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_bot_users', function (Blueprint $table) {
            //
            $table->dropColumn(['user_id','type']);
        });
    }
};
