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
        Schema::create('statements', function (Blueprint $table) {
            $table->id();
            $table->uuid('statement_id')->unique();
            $table->foreignId('actor_id')->constrained();
            $table->foreignId('activity_id')->constrained();
            $table->string('verb');
            $table->json('result')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('timestamp');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statements');
    }
};
