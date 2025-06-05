<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CompaniesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('companies',function(Blueprint $table){
            $table->id();
            $table->string('name_en',100);
            $table->string('name_km',150);
            $table->string('address',250);
            $table->string('email',100)->nullable();
            $table->string('company_type');
            $table->string('phone',25);
            $table->string('description',500)->nullable();
            $table->timestampTz("created_at")->useCurrent();
            $table->timestampTz("updated_at")->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('create_uid');
            $table->unsignedBigInteger('update_uid');
            /***
             * relationship
             */

            // $table->foreign('create_uid')->references('id')->on('users');
            // $table->foreign('update_uid')->references('id')->on('users');
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
        Schema::dropIfExists('company_profiles');
    }
}
