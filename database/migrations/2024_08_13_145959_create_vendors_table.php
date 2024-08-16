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
        Schema::create('vendors', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('name',50)->nullable();
            $table->string('name_kh',150)->nullable();
            $table->string('address',250)->nullable();
            $table->string('address_kh',300)->nullable();
            $table->string('phone',30);
            $table->string('email',100)->nullable();
            $table->unsignedBigInteger('vendor_type_id');
            $table->foreign('vendor_type_id')->references('id')->on('vendor_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vendors');
    }
};
