<?php

use App\Enums\TransferStatus;
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
        Schema::create('package_transfers', function (Blueprint $table) {
            $this->AddBaseFields($table);
            $table->dateTimeTz('transfer_datetime')->nullable();
            $table->dateTimeTz('est_arrive_datetime')->nullable();
            $table->integer('transfer_qty')->default(0);
            $table->string('remarks',300)->nullable();
            $table->integer('transfer_out_qty')->default(0);
            $table->unsignedBigInteger('status_id')->default(TransferStatus::PENDING);
            $table->unsignedBigInteger('transfer_uid');
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->string('driver_name',100)->nullable();
            $table->string('driver_phone',25)->nullable();
            $table->unsignedBigInteger('from_location_id')->nullable();
            $table->unsignedBigInteger('to_location_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_transfers');
    }
};
