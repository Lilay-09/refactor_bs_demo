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
        Schema::create('transaction_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('issue_year',10)->nullable();
            $table->string('month',10)->nullable();
            $table->string('prefix',15)->nullable();
            $table->unsignedInteger('last_idx');
            $table->string('payment_type')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->dateTimeTz('start_datetime')->default(now());
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_sequences');
    }
};
