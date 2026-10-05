<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('design_1_display_name', 150)->nullable()->after('display_number');
            $table->string('design_1_display_number', 2)->nullable()->after('design_1_display_name');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['design_1_display_name', 'design_1_display_number']);
        });
    }
};
