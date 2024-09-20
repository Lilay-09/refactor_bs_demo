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
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('ref_code',50)->nullable();
            $table->string('reason',350)->nullable();
            $table->string('type',50);
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('approved_uid')->nullable();
            $table->dateTime('approved_date')->nullable();
            $table->unsignedBigInteger('warehouse_id');

            $table->foreign('warehouse_id')->references('id')->on('stock_locations');
            $table->foreign('approved_uid')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
