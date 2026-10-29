<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->string('title');
            $t->text('instructions')->nullable();
            $t->json('questions')->nullable();
            $t->unsignedTinyInteger('pass_percent')->default(70);
            $t->unsignedTinyInteger('attempt_limit')->default(3);
            $t->boolean('required')->default(false);
            $t->timestamps();
        });
        Schema::create('assessment_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $t->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $t->json('snapshot');
            $t->json('answers');
            $t->unsignedTinyInteger('score');
            $t->boolean('passed');
            $t->timestamps();
        });
        Schema::create('assignment_submissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $t->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('original_name');
            $t->string('status')->default('pending');
            $t->text('feedback')->nullable();
            $t->foreignId('reviewer_id')->nullable()->constrained('users');
            $t->timestamps();
        });
        Schema::create('certificate_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->unique()->constrained()->cascadeOnDelete();
            $t->boolean('enabled')->default(false);
            $t->timestamps();
        });
        Schema::create('certificates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('enrollment_id')->unique()->constrained()->restrictOnDelete();
            $t->uuid('reference')->unique();
            $t->string('learner_name');
            $t->string('course_title');
            $t->timestamp('issued_at');
            $t->timestamp('revoked_at')->nullable();
            $t->text('revocation_reason')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['certificates', 'certificate_settings', 'assignment_submissions', 'assessment_attempts', 'assessments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
