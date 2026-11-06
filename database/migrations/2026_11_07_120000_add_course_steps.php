<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Courses become an ordered list of locked steps: a text page set, a video, a quiz or an assignment.
 * Each step unlocks only when the one before it is complete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table): void {
            $table->enum('type', ['video', 'text', 'quiz', 'assignment'])->default('video')->change();
        });
        Schema::table('lessons', function (Blueprint $table): void {
            // Text steps can be split into several pages the learner moves through in order.
            $table->json('pages')->nullable()->after('body');
            // Quiz and assignment steps point at the assessment that holds their questions / brief.
            $table->foreignId('assessment_id')->nullable()->after('video_asset_id')->constrained()->nullOnDelete();
        });
        Schema::table('assessments', function (Blueprint $table): void {
            // Assignments: "submit" lets the learner move on once submitted; "grade" waits for an accepted grade.
            $table->string('advance_on', 10)->default('grade')->after('required');
        });
        Schema::table('lesson_progress', function (Blueprint $table): void {
            // Furthest point actually watched (seeking back doesn't lower it) and text pages reached.
            $table->unsignedInteger('max_position_seconds')->default(0)->after('position_seconds');
            $table->unsignedSmallInteger('pages_viewed')->default(0)->after('watched_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_progress', fn (Blueprint $table) => $table->dropColumn(['max_position_seconds', 'pages_viewed']));
        Schema::table('assessments', fn (Blueprint $table) => $table->dropColumn('advance_on'));
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assessment_id');
            $table->dropColumn('pages');
        });
        Schema::table('lessons', function (Blueprint $table): void {
            $table->enum('type', ['video', 'text'])->default('video')->change();
        });
    }
};
