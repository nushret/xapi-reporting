<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
	// Sütunun var olup olmadığını kontrol et
            if (!Schema::hasColumn('activities', 'content_path')) {
                $table->string('content_path')->nullable();
            }
            
            if (!Schema::hasColumn('activities', 'launch_url')) {
                $table->string('launch_url')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
	$table->dropColumn(['launch_url']);
            
            if (Schema::hasColumn('activities', 'content_path')) {
                $table->dropColumn(['content_path']);
            }
        });
    }
};
