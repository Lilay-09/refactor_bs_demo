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
        Schema::create('disbursement_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('disbursement_id');
            $table->string('method',35);
            $table->decimal('amount',15,2)->default(0);
            $table->decimal('original_amount',15,2)->default(0);
            $table->string('currency_code',35)->default('USD');
            $table->timestampsTz();
            $table->foreign('disbursement_id')->references('id')->on('disbursements')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disbursement_details');
    }
};
