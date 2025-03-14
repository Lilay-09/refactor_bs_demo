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
        Schema::create('user_notification_tokens', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('user_id');
            $table->string('service_name',50)->nullable();
            $table->string('token')->nullable();
            $table->string('device_id',100)->nullable();
            $table->string('platform', 50)->nullable();
            $table->string('os_name', 300)->nullable();
            $table->boolean('is_active')->default(1);
            $table->dateTime('last_notified_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('subscribe_datetime')->nullable();
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::create('notification_topics', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->string('topic',50)->nullable();
            $table->string('type',50)->nullable();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->foreign('token_id')->references('id')->on('user_notification_tokens');
        });
        Schema::create('notifications', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->unsignedBigInteger('topic_id');
            $table->string('service_name',50)->nullable();
            $table->string('title')->nullable();
            $table->string('body',500)->nullable();
            $table->json('message_data')->nullable();
            $table->string('image_url',800)->nullable();
            $table->string('photo_file_name',200)->nullable();
            $table->string('status',50)->default('pending');
            $table->dateTime('sent_datetime')->nullable();
            $table->boolean('is_read')->nullable();
            $table->dateTime('read_datetime')->nullable();
            $table->foreign('topic_id')->references('id')->on('notification_topics');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_notification_tokens');
        Schema::dropIfExists('notification_topics');
        Schema::dropIfExists('notifications');
    }
};
