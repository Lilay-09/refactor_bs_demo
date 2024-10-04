<?php
namespace App\Traits;
use Illuminate\Database\Schema\Blueprint;
trait BaseMigrationField{
    public function AddBaseFields(Blueprint $table,$useIsDelete=true){
        $table->id();
        $table->timestampTz("created_at")->useCurrent();
        $table->timestampTz("updated_at")->useCurrent()->useCurrentOnUpdate();
        $table->unsignedBigInteger('create_uid');
        $table->unsignedBigInteger('update_uid');
        $table->unsignedBigInteger("branch_id");
        $table->unsignedBigInteger('company_id');

        /**
         * relationship
        */

        $table->foreign('create_uid')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('update_uid')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');


        /**
         * add soft delete if needed
         */

        if($useIsDelete){
            $table->boolean('is_deleted')->default(0);
            $table->unsignedBigInteger('deleted_uid')->nullable();
            $table->foreign('deleted_uid')->references('id')->on('users')->onDelete('cascade');
            $table->dateTime('deleted_datetime')->nullable();
        }
    }
}
