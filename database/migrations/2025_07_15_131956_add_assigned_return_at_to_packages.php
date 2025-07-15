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
            $table->dateTimeTz('assigned_return_at')
                ->nullable()
                ->after('return_at')
                ->comment('The date and time when the package was assigned for return, if applicable');
        });
        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->dateTimeTz('assigned_return_at')
                ->nullable()
                ->after('return_at')
                ->comment('The date and time when the package was assigned for return, if applicable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            //
            $table->dropColumn('assigned_return_at');
        });
        Schema::table('delivery_packages', function (Blueprint $table) {
            //
            $table->dropColumn('assigned_return_at');
        });
    }
};
