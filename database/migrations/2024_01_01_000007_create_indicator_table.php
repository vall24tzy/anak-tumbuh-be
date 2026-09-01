<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nama tabel singular ("indicator") mengikuti ERD final.
        // Tidak ada kolom created_at/updated_at sesuai ERD (sengaja diikuti apa adanya).
        Schema::create('indicator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained('habits')->cascadeOnDelete();
            $table->string('name');
            $table->integer('poin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator');
    }
};
