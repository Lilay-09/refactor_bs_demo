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
        Schema::table('companies', function (Blueprint $table) {
            //
            $table->string('address',250)->nullable()->change();
            $table->string('name',100)->nullable()->change();
            $table->string('name_km',150)->nullable()->change();
            $table->string('cp_name',150)->nullable();
            $table->string('cp_phone',150)->nullable();
            $table->string('cp_email',150)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            //
            DB::table('companies')->whereNull('address')->update(['address' => '']);

            // Revert the nullable changes
            $table->string('address', 250)->notNullable()->change();
            $table->string('name', 100)->notNullable()->change();
            $table->string('name_km', 150)->notNullable()->change();

            // Drop the additional columns
            $table->dropColumn(['cp_name', 'cp_phone', 'cp_email']);
        });
    }
};
