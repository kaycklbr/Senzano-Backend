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
        Schema::table('posts', function (Blueprint $table) {
            $table->text('image')->nullable()->after('slug');
        });
        
        Schema::table('pages', function (Blueprint $table) {
            $table->text('image')->nullable()->after('slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('image');
        });
        
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
