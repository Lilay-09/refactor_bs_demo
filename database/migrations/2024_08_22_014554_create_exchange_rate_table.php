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
        Schema::create('exchange_rate', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->decimal('buy_rate');
            $table->decimal('sell_rate');
            $table->date('x_date');
            $table->string('currency_pair')->default('USD-KHR');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rate');
    }
};
