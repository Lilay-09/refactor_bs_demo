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
        Schema::create('telegram_bot_users', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('bot_token')->nullable();
            $table->unsignedBigInteger('bot_id');
            $table->string('group_name')->nullable();
            $table->string('group_id',50)->nullable();
            $table->string('default_caption',250)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_bot_users');
    }
};
