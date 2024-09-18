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
        Schema::table('products', function (Blueprint $table) {
            //
            // $table->unsignedBigInteger('supplier_id')->nullable();
            // $table->string('photo_file_name',200)->nullable();
            // $table->foreign('supplier_id')->references('id')->on('vendors');
            if (!Schema::hasColumn('products', 'supplier_id')) {
                $table->unsignedBigInteger('supplier_id')->nullable();
                $table->foreign('supplier_id')->references('id')->on('vendors');
            }

            // Add `photo_file_name` only if it doesn't exist
            if (!Schema::hasColumn('products', 'photo_file_name')) {
                $table->string('photo_file_name', 200)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            //
            if (Schema::hasColumn('products', 'supplier_id')) {
                $table->dropForeign(['supplier_id']);
                $table->dropColumn('supplier_id');
            }

            // Remove `photo_file_name` column if it exists
            if (Schema::hasColumn('products', 'photo_file_name')) {
                $table->dropColumn('photo_file_name');
            }

        });
    }
};
