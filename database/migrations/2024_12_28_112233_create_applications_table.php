<?php

use App\Traits\BaseMigrationField;
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
        Schema::create('applications', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->nullable();
            $table->string('app_type')->default('admin-panel');
            $table->boolean('is_mobile_app')->default(false);
            $table->string('user_class')->default('admin');
            $table->timestampTz("created_at")->useCurrent();
            $table->timestampTz("updated_at")->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('create_uid')->nullable();
            $table->unsignedBigInteger('update_uid')->nullable();
            $table->unsignedBigInteger("branch_id")->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->boolean('is_deleted')->default(0);
            $table->unsignedBigInteger('deleted_uid')->nullable();
            $table->dateTime('deleted_datetime')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
