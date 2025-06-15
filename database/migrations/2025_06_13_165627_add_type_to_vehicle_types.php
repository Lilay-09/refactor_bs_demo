<?php

use App\Enums\VehicleType;
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
        Schema::table('vehicle_types', function (Blueprint $table) {
            //
            $table->string('type',25)->default(VehicleType::MOTO);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            //
            $table->dropColumn('type');
        });
    }
};
