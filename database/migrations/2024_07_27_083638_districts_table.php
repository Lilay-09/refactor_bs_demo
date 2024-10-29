<?php

use App\Traits\BaseMigrationField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class DistrictsTable extends Migration
{
    use BaseMigrationField;
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //
        Schema::create('districts',function(Blueprint $table){
            $this->AddBaseFields($table);
            $table->string('name',50)->nullable();
            $table->string('name_kh',100)->nullable();
            $table->unsignedBigInteger('city_id');

            //* relationship
            $table->foreign('city_id')->references('id')->on('cities')->onDelete('cascade');
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
        Schema::dropIfExists('districts');

    }
}
