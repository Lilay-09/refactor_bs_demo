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
        Schema::create('merchant_employees', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('name',100)->nullable();
            $table->string('gender',10)->nullable();
            $table->string('position')->default('Salesperson');
            $table->string('gender',10)->nullable();
            $table->string('phone',25)->nullable();
            $table->string('address')->nullable();
            $table->foreignId('merchant_id')->constrained('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_employees');
    }
};
