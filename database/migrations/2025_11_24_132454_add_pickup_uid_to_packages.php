<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedBigInteger('pickup_uid')->nullable()->after('order_id');
        });

        // Fill pickup_uid from orders table
        DB::statement("
            UPDATE packages
            SET pickup_uid = orders.driver_id
            FROM orders
            WHERE packages.order_id = orders.id
        ");

    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('pickup_uid');
        });
    }

};
