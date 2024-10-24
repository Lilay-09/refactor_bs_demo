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
        Schema::table('packages', function (Blueprint $table) {
            //
            $table->string('tracking_notes',500)->nullable();
            $table->string('return_notes',500)->nullable();
            $table->integer('cod_changed')->default(0);
            $table->decimal('taxi_fee',10,2)->default(0);
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            //
            $table->dropColumn(['taxi_fee','tracking_notes','return_notes','cod_changed']);
        });
    }
};
