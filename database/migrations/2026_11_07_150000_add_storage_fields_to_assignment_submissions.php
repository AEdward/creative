<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Student work can live on the server (small files) or in Cloudflare R2, and can be deleted to save space. */
    public function up(): void
    {
        Schema::table('assignment_submissions', function (Blueprint $table): void {
            $table->string('disk', 10)->default('local')->after('path');
            $table->unsignedBigInteger('bytes')->nullable()->after('original_name');
            $table->string('mime', 120)->nullable()->after('bytes');
            // The grade and feedback stay; only the file is removed.
            $table->timestamp('file_deleted_at')->nullable()->after('reviewer_id');
        });
    }

    public function down(): void
    {
        Schema::table('assignment_submissions', fn (Blueprint $table) => $table->dropColumn(['disk', 'bytes', 'mime', 'file_deleted_at']));
    }
};
