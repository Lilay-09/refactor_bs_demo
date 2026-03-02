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
        Schema::table('payment_transactions', function (Blueprint $table) {
            //
            $table->string('source',50)->nullable()->after('remarks');
            $table->jsonb('details')->nullable()->after('remarks');
            $table->index('details', null, 'gin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            //
            $table->dropIndex(['details']);
            $table->dropColumn([
                'source',
                'details'
            ]);
        });
    }
};
