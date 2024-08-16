<?php

use App\Traits\BaseMigrationField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('users',function(Blueprint $table){
            $table->id();
            $table->string('first_name',50);
            $table->string('last_name',50);
            $table->string('user_name',100)->nullable();
            $table->string('email',100)->nullable()->unique();
            $table->string('phone')->nullable()->unique();
            $table->string('password',300);
            $table->boolean('system_admin')->default(false);
            $table->boolean('lock')->default(false);
            $table->timestamp("created_at")->useCurrent();
            $table->timestamp("updated_at")->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('create_uid');
            $table->unsignedBigInteger('update_uid');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('company_id');

            // $table->foreign('company_id')->references('id')->on('companies');
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
        // Schema::dropIfExists('users');
    }
}
