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
        Schema::create('products', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('name',100);
            $table->string('code',100)->nullable();
            $table->string('description',250)->nullable();
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('group_id');

            $table->foreign('group_id')->references('id')->on('product_groups');
            $table->foreign('country_id')->references('id')->on('countries');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('model_id')->references('id')->on('models');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
