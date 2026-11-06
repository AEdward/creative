<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', fn (Blueprint $table) => $table->boolean('learning')->default(true));
    }

    public function down(): void
    {
        Schema::table('notification_preferences', fn (Blueprint $table) => $table->dropColumn('learning'));
    }
};
