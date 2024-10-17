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
        Schema::create('driver_commissions', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('driver_id');
            $table->string('delivery_type',35);
            $table->decimal('pickup_commission',10,2);
            $table->decimal('delivery_commission',10,2);
            $table->boolean('use_percentage')->default(false);
            $table->foreign('driver_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_commissions');
    }
};

