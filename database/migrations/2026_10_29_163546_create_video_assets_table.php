<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('object_key')->unique();
            $table->string('upload_key')->unique();
            $table->string('filename');
            $table->unsignedBigInteger('expected_bytes');
            $table->string('mime')->default('video/mp4');
            $table->string('status')->default('pending')->index();
            $table->timestamp('upload_expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_assets');
    }
};
