<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Admin accounts get a staff role: super (everything), content, support or finance. */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('staff_role', 20)->nullable()->after('role');
        });
        // Everyone who is an admin today keeps full access.
        DB::table('users')->where('role', 'admin')->update(['staff_role' => 'super']);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('staff_role'));
    }
};
