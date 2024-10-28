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
        Schema::create('default_remarks', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('channel',35)->nullable();
            $table->boolean('hidden')->default(0);
            $table->string('category',35);
            $table->string('remarks',250)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('default_remarks');
    }
};
