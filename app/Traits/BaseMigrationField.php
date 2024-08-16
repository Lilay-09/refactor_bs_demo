<?php
namespace App\Traits;
use Illuminate\Database\Schema\Blueprint;
trait BaseMigrationField{
    public function AddBaseFields(Blueprint $table,$useSoftDelete=false){
        $table->id();
        $table->timestamp("created_at")->useCurrent();
        $table->timestamp("updated_at")->useCurrent()->useCurrentOnUpdate();
        $table->unsignedBigInteger('create_uid');
        $table->unsignedBigInteger('update_uid');
        $table->unsignedBigInteger("branch_id");
        $table->unsignedBigInteger('company_id');

        /**
         * relationship
        */

        $table->foreign('create_uid')->references('id')->on('users');
        $table->foreign('update_uid')->references('id')->on('users');
        $table->foreign('branch_id')->references('id')->on('branches');
        $table->foreign('company_id')->references('id')->on('companies');


        /**
         * add soft delete if needed
         */

        // if($useSoftDelete){
        //     $table->softDeletes();
        //     $table->unsignedInteger("delete_uid");
        // }
    }
}
