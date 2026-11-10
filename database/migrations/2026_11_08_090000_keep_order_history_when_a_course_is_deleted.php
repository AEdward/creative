<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super admins may delete a course that people bought. Order lines keep their title_snapshot and
 * price for the books; their course link becomes empty instead of blocking the delete.
 * Course-specific coupons that were used are kept (deactivated) for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $t) {
            $t->dropForeign(['course_id']);
        });
        Schema::table('order_items', function (Blueprint $t) {
            $t->foreignId('course_id')->nullable()->change();
            $t->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
        });
        Schema::table('coupons', function (Blueprint $t) {
            $t->dropForeign(['course_id']);
        });
        Schema::table('coupons', function (Blueprint $t) {
            $t->foreign('course_id')->references('id')->on('courses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $t) {
            $t->dropForeign(['course_id']);
            $t->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
        });
        Schema::table('order_items', function (Blueprint $t) {
            $t->dropForeign(['course_id']);
            $t->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
        });
    }
};
