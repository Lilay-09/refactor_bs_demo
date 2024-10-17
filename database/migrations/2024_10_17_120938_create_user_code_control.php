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
        Schema::create('user_code_control', function (Blueprint $table) {
            $table->unsignedInteger('last_id');
            $table->string('prefix',35);
            $table->year('issue_year')->nullable();
            $table->unsignedBigInteger("branch_id");
            $table->unsignedBigInteger('company_id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_code_control');
    }
};
