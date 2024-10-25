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
            $table->boolean('is_contact')->default(0);
            $table->string('contact_reason',500)->nullable();
            $table->integer('priority_level')->default(0);
        });

        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->boolean('is_contact')->default(0);
            $table->string('contact_reason',500)->nullable();
            $table->integer('priority_level')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            //
            $table->dropColumn(['is_contact','contact_reason','priority_level']);
        });

        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->dropColumn(['is_contact','contact_reason','priority_level']);
        });
    }
};
