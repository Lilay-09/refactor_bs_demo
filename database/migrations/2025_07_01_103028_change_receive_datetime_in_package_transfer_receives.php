<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('package_transfer_receives', function (Blueprint $table) {
            // Step 1: Add a temporary column
            $table->dateTimeTz('receive_date_tmp')->nullable()->default(now());
        });

        // Step 2: Copy values with cast
        DB::statement("UPDATE package_transfer_receives SET receive_date_tmp = receive_datetime::timestamptz");

        Schema::table('package_transfer_receives', function (Blueprint $table) {
            // Step 3: Drop the old column
            $table->dropColumn('receive_datetime');
        });

        Schema::table('package_transfer_receives', function (Blueprint $table) {
            // Step 4: Rename temp column to final name
            $table->renameColumn('receive_date_tmp', 'receive_date');
        });
    }

    public function down(): void
    {
        Schema::table('package_transfer_receives', function (Blueprint $table) {
            // Step 1: Add the old column back
            $table->string('receive_datetime')->nullable();
        });

        // Step 2: Copy data back as string (formatted)
        DB::statement("UPDATE package_transfer_receives SET receive_datetime = receive_date::text");

        Schema::table('package_transfer_receives', function (Blueprint $table) {
            // Step 3: Drop the new column
            $table->dropColumn('receive_date');
        });

        Schema::table('package_transfer_receives', function (Blueprint $table) {
            // Step 4: Rename back if needed
            // In this case, we already renamed in step 1
        });
    }

};
