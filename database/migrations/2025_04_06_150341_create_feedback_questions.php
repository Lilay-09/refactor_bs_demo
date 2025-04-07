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
        Schema::create('feedback_questions', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('form_id');
            $table->string('question_en',150)->nullable();
            $table->string('question_km',150)->nullable();
            $table->unsignedInteger('display_order')->default(1);
            $table->foreign('form_id')->references('id')->on('feedback_forms')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_questions');
    }
};
