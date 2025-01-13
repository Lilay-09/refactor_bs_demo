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
        Schema::create('package_attachments', function (Blueprint $table) {
            $table->unsignedBigInteger('package_id');
            $table->string('file_name',250)->nullable();
            $table->string('file_type',25)->default('image');
            $table->string('remarks',350)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_attachments');
    }
};
