<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', fn (Blueprint $table) => $table->longText('description')->nullable()->change());
        Schema::table('lessons', fn (Blueprint $table) => $table->longText('body')->nullable()->change());
    }

    public function down(): void
    {
        // Retain expanded capacity during rollback so valid Unicode content is not truncated.
    }
};
