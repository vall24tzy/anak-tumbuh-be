<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// DB Design ANAKTUMBUH.ID v1.5 FINAL — Bagian 5.10 (habits)
// FIXED reference table — tidak ada CRUD untuk role manapun (API Contract v1.2 §21).
// Tanpa timestamps sesuai ERD (tabel ini tidak pernah di-update setelah seeding awal).

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 30)->unique();
            $table->string('name', 50)->unique();
            $table->boolean('allows_multi_select')->default(false);
            $table->smallInteger('sort_order')->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habits');
    }
};
