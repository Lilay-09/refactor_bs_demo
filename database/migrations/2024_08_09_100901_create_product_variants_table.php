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
        Schema::create('product_variants', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('product_id');
            $table->string('size',30)->nullable();
            $table->string('color',30)->nullable();
            $table->string('sku',50)->nullable();
            $table->string('weight',30)->nullable();
            $table->string('width',30)->nullable();
            $table->string('length',30)->nullable();
            $table->date('expires_at')->nullable();
            $table->string('condition',30)->default('new');
            $table->string('condition_percentage',10)->default('100%');

            //
            $table->foreign('product_id')->references('id')->on('products');
        });

    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
