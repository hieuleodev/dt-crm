<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ghi lại cấu trúc ban đầu của quyền phân hệ theo tài khoản trong lịch sử migration.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('work_permissions')->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('work_permissions');
        });
    }
};
