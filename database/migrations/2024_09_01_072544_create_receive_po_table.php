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
        Schema::create('receive_po', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('receive_number')->nullable();
            $table->unsignedBigInteger('receive_uid');
            $table->unsignedBigInteger('purchase_id');
            $table->date('receive_date')->default(now());
            $table->foreign('receive_uid')->references('id')->on('users');
            $table->foreign('purchase_id')->references('id')->on('purchase_orders');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receive_po');
    }
};
