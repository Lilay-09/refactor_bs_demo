<?php

use App\Traits\BaseMigrationField;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use BaseMigrationField;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('price_lists', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->decimal('price',10,2)->default(0);
            $table->
            $table->boolean('apply_all')->default(false);
            $table->boolean('status')->default(true);
        });

        Schema::create('price_list_zones', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->boolean('status')->default(true);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('price_list_zones');
    }
};
