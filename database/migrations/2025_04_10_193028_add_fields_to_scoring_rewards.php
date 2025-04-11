<?php

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
        Schema::table('scoring_rewards', function (Blueprint $table) {
            //
            $table->unsignedInteger('claim_type_id')->nullable();
            $table->string('unit',30)->nullable();
            $table->jsonb('list')->nullable();
            $table->decimal('unit_amount',15,2)->default(0);
            $table->unsignedInteger('max_usage')->nullable()->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scoring_rewards', function (Blueprint $table) {
            //
            $table->dropColumn(['claim_type_id','unit_amount','unit','list','max_usage']);
        });
    }
};
