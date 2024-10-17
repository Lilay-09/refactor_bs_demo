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
            $table->string('code',35)->nullable();
            $table->string('first_name',50)->nullable();
            $table->string('last_name',50)->nullable();
            $table->string('user_name',100)->nullable();
            $table->string('name_km',100)->nullable();
            $table->string('photo_file_name',200)->nullable();
            $table->string('email',100)->nullable();
            $table->string('gender',7)->nullable();
            $table->string('phone',25)->nullable();
            $table->string('bio',500)->nullable();
            $table->decimal('latitude',9,6)->nullable();
            $table->decimal('longtitude',9,6)->nullable();
            $table->string('address',500)->nullable();
            $table->date('dob')->nullable();
            $table->string('otp',20)->nullable();
            $table->dateTime('otp_expiration')->nullable();
            $table->dateTime('last_login')->nullable();
            $table->string('password',300)->nullable();
            $table->string('national_id',35)->nullable();
            $table->boolean('system_admin')->default(false);
            $table->boolean('lock')->default(false);
            $table->boolean('delete_account')->default(false);
            $table->boolean('has_account')->default(false);
            $table->boolean('is_deleted')->default(0);
            $table->unsignedBigInteger('deleted_uid')->nullable();
            $table->foreign('deleted_uid')->references('id')->on('users')->onDelete('cascade');
            $table->dateTime('deleted_datetime')->nullable();
            $table->string('account_type',50)->default('admin');
            $table->timestampTz("created_at")->useCurrent();
            $table->string('plate_number',35)->nullable();
            $table->string('shift_type',25)->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('vehicle_type',30)->nullable();
            $table->unsignedBigInteger('driver_warehouse_id')->nullable();
            $table->timestampTz("updated_at")->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('create_uid');
            $table->unsignedBigInteger('update_uid');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('company_id');
            $table->dateTime('employment_date')->nullable();
            $table->string('relative_name',50)->nullable();
            $table->string('relative_phone',25)->nullable();
            $table->string('relative_relationship',50)->nullable();
            $table->string('relative_address',500)->nullable();
            $table->decimal('salary',10,2)->default(0);
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
        Schema::dropIfExists('users');
    }
}
