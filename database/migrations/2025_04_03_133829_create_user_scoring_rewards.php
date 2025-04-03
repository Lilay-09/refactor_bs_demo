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
        Schema::create('user_scoring_rewards', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('target_package');
            $table->unsignedInteger('target_package_status_id');
            $table->decimal('reward_amount',15,2)->default(0);
            $table->unsignedBigInteger('reward_id')->nullable();
            $table->dateTimeTz('start_date')->nullable();
            $table->string('currency_code',25)->default('USD');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_scoring_rewards');
    }
};
