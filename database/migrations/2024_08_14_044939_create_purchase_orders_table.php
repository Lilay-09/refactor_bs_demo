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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('vendor_id');
            $table->string('po_code')->unique()->nullable();
            $table->date('issue_date');
            $table->decimal('tax')->default(0);
            $table->decimal('discount_amount')->default(0);
            $table->string('discount_type')->default('%');
            $table->decimal('total_amount')->default(0);
            $table->decimal('due_amount')->default(0);
            $table->decimal('paid_amount')->default(0);
            // $table->boolean('approved')->default(false);
            $table->date('approve_date')->nullable();
            $table->unsignedBigInteger('approve_uid')->nullable();
            $table->string('remarks',250)->nullable();
            $table->date('expect_arrival_date')->nullable();
            // $table->boolean('canceled')->default(false);
            $table->date('cancel_date')->nullable();
            $table->string('cancel_remarks',250)->nullable();
            $table->tinyInteger('status_id')->default(1);
            $table->unsignedBigInteger('receive_uid')->nullable();
            $table->unsignedBigInteger('reject_uid')->nullable();
            $table->unsignedBigInteger('reject_remarks')->nullable();

            //*
            $table->foreign('vendor_id')->references('id')->on('vendors');
            $table->foreign('status_id')->references('id')->on('purchase_statuses');
            $table->foreign('receive_uid')->references('id')->on('users');
            $table->foreign('reject_uid')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
