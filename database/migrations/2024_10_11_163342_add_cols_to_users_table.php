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
            $table->string('business_type',50)->nullable();
            $table->unsignedBigInteger('client_type_id')->nullable();
            $table->foreign('client_type_id')->references('id')->on('client_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
            $table->dropForeign([
                'client_type_id'
            ]);
            $table->dropColumn([
                'business_type',
                'client_type_id'
            ]);

        });
    }
};
