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
        Schema::create('zones', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('code',35)->nullable();
            $table->string('type',35)->nullable();
            $table->string('name',35)->nullable();
            $table->string('commune',150)->comment('sangkat')->nullable();
            $table->string('district',150)->comment('khan')->nullable();
            $table->string('city',150)->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('desctiption',500)->nullable();
            $table->boolean('status')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
