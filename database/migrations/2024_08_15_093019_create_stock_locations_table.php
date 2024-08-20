<?php

use App\Traits\BaseMigrationField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use BaseMigrationField;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('type_id');
            $table->string('description',250)->nullable();
            $table->string('address',150)->nullable();
            $table->string('address_kh',200)->nullable();
            $table->boolean('main')->default(0);
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('create_uid');
            $table->unsignedBigInteger('update_uid');
            //*
            $table->foreign('type_id')->references('id')->on('stock_location_types');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('create_uid')->references('id')->on('users');
            $table->foreign('update_uid')->references('id')->on('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_locations');
    }
};
