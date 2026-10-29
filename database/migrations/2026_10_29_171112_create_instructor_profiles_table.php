<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('professional_title', 120)->nullable();
            $table->text('biography')->nullable();
            $table->text('specialties')->nullable();
            $table->boolean('publicly_listed')->default(false)->index();
            $table->boolean('show_photo')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_profiles');
    }
};
