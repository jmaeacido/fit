<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('display_name', 150)->nullable();
            $table->string('display_number', 2)->nullable();
        });
    }
    public function down(): void {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'display_number']);
        });
    }
};
