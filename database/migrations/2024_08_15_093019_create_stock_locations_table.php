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
            $this->AddBaseFields($table);
            $table->string('name');
            $table->unsignedBigInteger('type_id');
            $table->string('description',250)->nullable();
            $table->string('address',150)->nullable();
            $table->string('address_kh',200)->nullable();
            //*
            $table->foreign('type_id')->references('id')->on('stock_location_types');
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
