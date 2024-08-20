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
        Schema::create('po_payments', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->decimal('amount');
            $table->unsignedBigInteger('vendor_id');
            $table->string('description',250)->nullable();
            $table->date('payment_date')->default(now());
            $table->string('currency')->default('$');
            $table->foreign('vendor_id')->references('id')->on('vendors');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('po_payments');
    }
};
