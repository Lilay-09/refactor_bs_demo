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
        Schema::table('user_bank_accounts', function (Blueprint $table) {
            //
            $table->string('whitelist_to',50)->nullable();
            $table->boolean('is_whitelist')->default(false);
            $table->unsignedBigInteger('whitelist_by')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_bank_accounts', function (Blueprint $table) {
            //
            $table->dropColumn(['whitelist_to','is_whitelist','whitelist_by']);
        });
    }
};
