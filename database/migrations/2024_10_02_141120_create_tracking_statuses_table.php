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
        Schema::create('tracking_statuses', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('name',50)->nullable();
            $table->string('stage',50)->nullable();
            $table->boolean('hidden')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_statuses');
    }
};
