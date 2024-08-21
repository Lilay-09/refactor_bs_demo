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
        Schema::create('receipt_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->foreignId('receipt_id')->constrained()->onDelete('cascade');
            $table->integer('qty');
            $table->decimal('unit_price');
            $table->decimal('net_amount');
            $table->decimal('cost')->default(0);
            $table->decimal('tax')->default(0);
            $table->decimal('discount_amount')->default(0);
            $table->string('discount_type')->default('%');
            $table->string('description',250)->nullable();
            $table->timestamps();
            //
            $table->foreign('service_id')->references('id')->on('services');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipt_services');
    }
};
