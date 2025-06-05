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
        Schema::create('business_types', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('name_en',50)->nullable();
            $table->string('name_km',50)->nullable();
            $table->string('description_en',300)->nullable();
            $table->string('description_km',300)->nullable();
            $table->boolean('hidden')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_types');
    }
};
