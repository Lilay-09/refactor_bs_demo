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
        Schema::create('user_shops', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            $table->string('name_en',100)->nullable();
            $table->string('name_km',100)->nullable();
            $table->string('shop_type',35)->nullable();
            $table->string('address')->nullable();
            $table->text('pin_address')->nullable();
            $table->decimal('loc_lat',19,7)->default(0);
            $table->decimal('loc_lng',19,7)->default(0);
            $table->string('phone',25)->nullable();
            $table->string('product_type',25)->nullable();
            $table->unsignedInteger('est_pcs')->default(0);
            $table->string('email',100)->nullable();
            $table->string('disclaimer',100)->nullable();
            $table->string('image')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
            $table->string('city',50)->nullable();
            $table->string('district',50)->nullable();
            $table->string('commune',50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_shops');
    }
};
