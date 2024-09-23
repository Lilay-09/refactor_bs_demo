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
        Schema::table('invoices', function (Blueprint $table) {
            //
            $table->decimal('change',10,2)->default(0);
            $table->decimal('change_kh',10,2)->default(0);
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            //
            $table->decimal('amount_kh',10,2)->default(0);
            $table->string('currency',25)->default('USD');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Dropping 'change' and 'change_kh' columns from 'invoices' table
            $table->dropColumn(['change', 'change_kh']);
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            // Dropping 'amount_kh' column from 'invoice_payments' table
            $table->dropColumn(['amount_kh','currency']);
        });
    }
};
