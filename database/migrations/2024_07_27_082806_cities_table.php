<?php

use App\Traits\BaseMigrationField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CitiesTable extends Migration
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
        Schema::create('cities',function(Blueprint $table){
            $this->AddBaseFields($table);
            $table->string('name_en',50)->nullable();
            $table->string('name_km',100)->nullable();
            $table->unsignedBigInteger('country_id');

            //* relationship
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('cascade');
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
        Schema::dropIfExists('cities');
    }
}
