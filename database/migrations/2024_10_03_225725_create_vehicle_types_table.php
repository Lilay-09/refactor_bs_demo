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
        Schema::create('vehicle_types', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('name_en',80)->nullable();
            $table->string('name_km',80)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};
