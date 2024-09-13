<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{


    public function up()
    {


        // Get all tables in the database
        $tables = DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = ?', ['public']);

        foreach ($tables as $table) {
            $tableName = $table->table_name;
            Schema::table($tableName, function (Blueprint $table) {
                // Add the new column (e.g., a string column)
                $table->boolean('void')->default(0);
                $table->unsignedBigInteger('void_uid')->nullable();
            });

        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {

        // Get all tables in the database
        $tables = DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = ?', ['public']);

        foreach ($tables as $table) {
            $tableName = $table->table_name;
            Schema::table($tableName, function (Blueprint $table) {
                // Drop the column
                $table->dropColumn(['void','void_uid']);
            });

        }
    }
};
