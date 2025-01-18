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
        Schema::table('roles', function (Blueprint $table) {
            $table->string('group',25)->default('admin');
            $table->boolean('is_deleted')->default(0);
            $table->unsignedBigInteger('deleted_uid')->nullable();
            $table->dateTime("deleted_datetime")->nullable();

        });
        DB::table('roles')->whereIn('id',[2,3])->update([
            'group' => 'mobile',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            //
            $table->dropColumn(['group','is_deleted','deleted_datetime','deleted_uid']);
        });
    }
};
