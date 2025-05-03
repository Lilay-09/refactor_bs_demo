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
        Schema::create('user_target_policies', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('target_value')->default(0);
            $table->string('target_type',35)->nullable();
            $table->decimal('monthly_bonus',15,2)->default(0);
            $table->string('monthly_bonus_type',35)->nullable();
            $table->decimal('yearly_bonus',15,2)->default(0);
            $table->string('yearly_bonus_type',35)->nullable();
            $table->dateTimeTz('effective_date')->nullable();
            $table->string('period_type',35)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_target_policies');
    }
};
