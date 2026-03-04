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
        Schema::table('banners', function (Blueprint $table) {
            //
            $table->text('description_km')->nullable()->after('description');
            $table->text('description')->nullable()->change();
            $table->string('contact_link',550)->nullable();
            $table->string('cover_file_name',250)->nullable()->after('photo_file_name');
            $table->string('title_km',100)->nullable()->after('title');
            $table->boolean('is_publish')->default(0)->after('description_km');
            $table->dateTime('start_date')->nullable()->after('is_publish');
            $table->dateTime('end_date')->nullable()->after('start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            //
            $table->dropColumn([
                'description_km',
                'contact_link',
                'cover_file_name',
                'title_km',
                'is_publish',
                'start_date',
                'end_date'
            ]);
            $table->string('description',350)->nullable()->change();
        });
    }
};
