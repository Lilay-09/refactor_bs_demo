<?php

use App\Enums\PaymentStatus;
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
        Schema::table('disbursements', function (Blueprint $table) {
            //
            $table->decimal('amount_due_usd',15,2)->default(0);
            $table->decimal('amount_due_khr',15,2)->default(0);
            $table->decimal('received_amount_usd',15,2)->default(0);
            $table->decimal('received_amount_khr',15,2)->default(0);
            $table->unsignedInteger('payment_status_id')->default(PaymentStatus::DONE->value);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disbursements', function (Blueprint $table) {
            //
            $table->dropColumn([
                'amount_due_usd',
                'amount_due_khr',
                'received_amount_usd',
                'received_amount_khr',
                'payment_status_id'
            ]);
        });
    }
};
