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
        Schema::create('disbursement_packages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id');
            $table->unsignedBigInteger('disbursement_id');
            $table->string('type',35);
            $table->string('payee_type',35);
            $table->boolean('is_deleted')->default(0);
            $table->dateTimeTz('deleted_datetime')->nullable();
            $table->unsignedBigInteger('deleted_uid')->nullable();
            $table->string('deleted_reason')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disbursement_packages');
    }
};
