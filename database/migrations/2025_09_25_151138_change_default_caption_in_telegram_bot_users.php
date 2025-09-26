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
            $table->string('default_caption',500)->change()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('telegram_bot_users', function (Blueprint $table) {
            //
            $table->string('default_caption',250)->change()->nullable();
        });
    }
};
