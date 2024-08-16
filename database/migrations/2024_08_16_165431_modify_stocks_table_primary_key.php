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
        //
        Schema::table('stocks', function (Blueprint $table) {
            // Drop the primary key constraint on 'sku'
            $table->dropPrimary('sku');

            // Add a new 'id' column as the primary key
            $table->bigIncrements('id')->first();

            // Optionally, add the unique constraint to 'sku' if needed
            $table->string('sku', 50)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::table('stocks', function (Blueprint $table) {
            // Remove the 'id' column
            $table->dropColumn('id');

            // Reinstate 'sku' as the primary key
            $table->primary('sku');
        });
    }
};
