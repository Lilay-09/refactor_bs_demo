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
        Schema::table('zones', function (Blueprint $table) {
            // Correcting the typo and changing the column definition
            $table->renameColumn('desctiption', 'description');
            $table->string('description', 500)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            // Reverting the column back to the misspelled version
            $table->renameColumn('description', 'desctiption');
            $table->string('desctiption', 500)->nullable()->change();
        });
    }

};
