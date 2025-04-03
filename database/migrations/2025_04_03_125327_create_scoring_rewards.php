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
        Schema::create('scoring_rewards', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('code',100)->nullable();
            $table->string('channel',35)->nullable();
            $table->string('mission',50)->nullable();
            $table->string('message')->nullable();
            $table->string('description')->nullable();
            $table->dateTimeTz('start_date')->nullable();
            $table->decimal('amount',15,2)->default(0);
            $table->string('amount_type')->default('amount');
            $table->dateTimeTz('expiration_date')->nullable();
            $table->string('image')->nullable();
            $table->string('reward_type')->nullable();
            $table->string('currency_code',25)->default('USD');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scoring_rewards');
    }
};
