<?php

use App\Enums\LocationType;
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
        Schema::create('package_transfer_receives', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('from_location_id');
            $table->unsignedBigInteger(column: 'receive_uid');
            $table->string('location_type',50)->default(LocationType::WAREHOUSE);
            $table->string('from_location_type',50)->default(LocationType::WAREHOUSE);
            $table->unsignedInteger('qty')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_transfer_receives');
    }
};
