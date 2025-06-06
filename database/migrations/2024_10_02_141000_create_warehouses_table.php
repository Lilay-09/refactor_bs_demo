<?php

use App\Enums\Enums\WarehouseType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name_en',100)->nullable();
            $table->string('address_en',350)->nullable();
            $table->string('bm_phone',30)->nullable();
            $table->string('bm_name_en',50)->nullable();
            $table->string('bm_name_km',50)->nullable();
            $table->decimal('loc_lat',9,6)->nullable();
            $table->decimal('loc_lng',9,6)->nullable();
            $table->integer('staff_count')->default(0);
            $table->boolean('inactive')->default(false);
            $table->text('google_map_link')->nullable();
            $table->unsignedBigInteger('warehouse_type_id')->default(WarehouseType::CENTRAL_WAREHOUSE);
            $table->unsignedBigInteger('create_uid')->nullable();
            $table->unsignedBigInteger('update_uid')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->timestamps();


            $table->foreign('create_uid')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('update_uid')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
