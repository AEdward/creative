<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Support chats stay open until staff close them; the next message after that starts a new chat. */
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('status', 10)->default('open')->after('subject')->index();
            $table->timestamp('closed_at')->nullable()->after('status');
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['status', 'closed_at']);
        });
    }
};
