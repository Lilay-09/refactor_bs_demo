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
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name',100)->nullable();
            $table->string('address',350)->nullable();
            $table->string('cp_phone',30)->nullable();
            $table->string('cp_name',30)->nullable();
            $table->decimal('loc_lat',9,6)->nullable();
            $table->decimal('loc_lng',9,6)->nullable();
            $table->string('warehouse_type',30)->default('normal');
            $table->unsignedBigInteger('create_uid')->nullable();
            $table->unsignedBigInteger('update_uid')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();


            $table->foreign('create_uid')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('update_uid')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
