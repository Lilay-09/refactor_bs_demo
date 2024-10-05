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
        Schema::create('price_list', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->decimal('price',10,2)->default(0);
            $table->string('currency_code',20)->default('USD');
            $table->decimal('base_fee',10,2)->default(1.25);
            $table->decimal('below_kg',10,2)->default(0);
            $table->decimal('below_kg_price',10,2)->default(0);
            $table->decimal('above_kg',10,2)->default(0);
            $table->decimal('above_kg_price',10,2)->default(0);
            $table->string('delivery_type',35)->default('normal');
            $table->boolean('apply_all_zones')->default(false);
            $table->boolean('status')->default(true);
        });

        Schema::create('price_list_zones', function (Blueprint $table) {
            $table->unsignedBigInteger('price_list_id');
            $table->unsignedBigInteger('zone_id');
            $table->foreign('price_list_id')->references('id')->on('price_list')->onDelete('cascade');
            $table->foreign('zone_id')->references('id')->on('zones')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_list');
        Schema::dropIfExists('price_list_zones');
    }
};
