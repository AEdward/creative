<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->string('kind', 20);
            $table->string('language', 5)->nullable();
            $table->string('object_key')->unique();
            $table->string('mime', 100);
            $table->string('extension', 10);
            $table->unsignedBigInteger('bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_resources');
    }
};
