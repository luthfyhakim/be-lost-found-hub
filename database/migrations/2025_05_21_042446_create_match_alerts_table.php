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
        Schema::create('match_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lost_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('found_item_id')->constrained()->onDelete('cascade');
            $table->decimal('match_score', 8, 2);
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_alerts');
    }
};
