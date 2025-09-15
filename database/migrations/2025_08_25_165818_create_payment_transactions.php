<?php

use App\Enums\Currency;
use App\Enums\TransactionType;
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
        Schema::create('payment_transactions', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->dateTimeTz('payment_date');
            $table->unsignedBigInteger('approved_uid');
            $table->string('payment_ref',50);
            $table->unsignedBigInteger('payment_id');
            $table->string('transaction_type')->nullable()->default(TransactionType::TRANSFER_IN->value);
            $table->string('currency',25)->default(Currency::USD->value);
            $table->decimal('amount',17,5)->default(0);
            $table->string('remarks')->nullable();
            $table->string('from_account',100)->nullable();
            $table->string('to_account',100)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
