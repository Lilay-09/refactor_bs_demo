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
        Schema::create('comment_descriptions', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('thread_id', 50)->index()->nullable();
            $table->unsignedBigInteger('comment_id');
            $table->string('tmp_id', 50)->nullable()->index();
            $table->text('data')->nullable();
            $table->string('data_type', 50)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comment_descriptions');
    }
};
