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
        Schema::table('package_attachments', function (Blueprint $table) {
            //
            $table->boolean('hidden')->default(false);
            $table->unsignedBigInteger('submit_uid')->nullable();
            $table->string('user_class',25)->default('driver');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('package_attachments', function (Blueprint $table) {
            //
            $table->dropColumn(['hidden','submit_uid','user_class']);
        });
    }
};
