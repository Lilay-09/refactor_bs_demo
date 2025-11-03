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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('group',50)->nullable();
            $table->unsignedBigInteger('user_id')->nullable(); // actor
            $table->string('action',30); // create, update, delete
            $table->string('module',100)->nullable(); // table or module
            $table->unsignedBigInteger('ref_id')->nullable(); // record id
            $table->string('ref_code',100)->nullable();
            $table->jsonb('before')->nullable(); // old values
            $table->jsonb('after')->nullable(); // new values
            $table->jsonb('metadata')->nullable(); 
            $table->timestamps();
            $table->index('user_id');
            $table->index(['module', 'ref_id','group']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
