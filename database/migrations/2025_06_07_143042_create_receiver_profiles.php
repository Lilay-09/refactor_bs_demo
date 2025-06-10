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
        Schema::create('receiver_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('address')->nullable();
            $table->text('google_map_url')->nullable();
            $table->string('photo_file_name')->nullable();
            $table->boolean('is_editable');
            $table->unsignedBigInteger('create_uid');
            $table->unsignedBigInteger('update_uid');
            $table->unsignedBigInteger('deleted_uid')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->string('deleted_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receiver_profiles');
    }
};
