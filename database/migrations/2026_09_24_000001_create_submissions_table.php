<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->uuid('request_key')->unique();
            $table->string('name', 150);
            $table->string('size', 3)->index();
            $table->json('designs');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('submissions'); }
};
