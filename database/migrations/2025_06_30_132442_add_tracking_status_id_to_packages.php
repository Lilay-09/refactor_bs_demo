<?php

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
        Schema::table('packages', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('tracking_status_id')->nullable();
        });
        Schema::table('packages', function (Blueprint $table) {
            //
            DB::table('packages')->where('is_deleted', false)->update([
                'tracking_status_id' => DB::raw('status_id')
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            //
            $table->dropColumn([
                'tracking_status_id'
            ]);
        });
    }
};
