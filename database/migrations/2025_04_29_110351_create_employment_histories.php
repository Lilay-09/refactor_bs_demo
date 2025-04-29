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
        Schema::create('employment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Employment type changes
            $table->string('previous_employment_type')->nullable();
            $table->string('new_employment_type');

            // Payment changes (commission %, salary, hourly rate, etc.)
            $table->decimal('previous_rate', 10, 2)->nullable();
            $table->decimal('new_rate', 10, 2)->nullable();

            // If currency matters (for multi-country apps)
            $table->string('currency', 3)->default('USD'); // Example: USD, THB, EUR

            // Employment status (optional)
            $table->string('previous_status')->nullable(); // active, inactive, suspended, etc.
            $table->string('new_status')->nullable();

            // Related position or department change
            $table->string('previous_position')->nullable();
            $table->string('new_position')->nullable();
            $table->string('previous_department')->nullable();
            $table->string('new_department')->nullable();

            // Effective date of the change (can be different from created_at)
            $table->date('effective_date')->nullable();

            // Admin/staff who made the change (optional, if admin system)
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();

            // Note/reason
            $table->text('note')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_histories');
    }
};
