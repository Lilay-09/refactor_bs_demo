<?php

use App\Enums\Enums\BranchType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class BranchesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('branch_type_id')->default(BranchType::HEAD_OFFICE);
            $table->string('name_en',100)->nullable();
            $table->string('name_km',150)->nullable();
            $table->string('address_en',250)->nullable();
            $table->string('address_km',250)->nullable();
            $table->string('bm_name_en',100)->nullable();
            $table->string('bm_name_km',100)->nullable();
            $table->string('bm_phone',25)->nullable();
            $table->string('email',100)->nullable();
            $table->string('phone',25)->nullable();
            $table->string('emergency_phone',25)->nullable();
            $table->integer('staff_count')->default(0);
            $table->unsignedBigInteger('company_id');
            $table->string('description_en',500)->nullable();
            $table->string('description_km',500)->nullable();
            $table->timestampTz("created_at")->useCurrent();
            $table->timestampTz("updated_at")->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('create_uid');
            $table->unsignedBigInteger('update_uid');
            /***
             * relationship
             */
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');

            $table->boolean('is_deleted')->default(0);
            $table->unsignedBigInteger('deleted_uid')->nullable();
            $table->string('deleted_reason')->nullable();
            $table->dateTime('deleted_datetime')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
        Schema::dropIfExists('branches');
    }
}
