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
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('finished_reason',500)->nullable();
            $table->dateTime('finished_datetime')->nullable();
            $table->unsignedBigInteger('finished_uid')->nullable();
            $table->text('tracking_notes')->nullable();
            $table->boolean('finished')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['finished_reason','finished','finished_datetime','finished_uid','tracking_notes']);
        });
    }
};
