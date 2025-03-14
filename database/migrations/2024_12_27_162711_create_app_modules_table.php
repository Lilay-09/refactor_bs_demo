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
        Schema::create('app_modules', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('code',35)->nullable();
            $table->string('name',100)->nullable();
            $table->string('name_kh',150)->nullable();
            $table->string('native_name',100)->nullable();
            $table->string('native_name_kh',150)->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('hidden')->default(false);
            $table->string('icon',250)->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_modules');
    }
};
