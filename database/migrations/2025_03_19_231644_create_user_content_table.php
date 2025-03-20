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
        Schema::create('user_content', function (Blueprint $table) {
		$table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('content_path');
            $table->timestamps();
            
            $table->unique(['user_id', 'content_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_content');
    }
};
