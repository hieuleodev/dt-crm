<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bỏ cột cấu hình theo tài khoản khi các phân hệ chuyển sang danh sách dùng chung.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'work_permissions')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('work_permissions');
            });
        }
    }

    /**
     * Khôi phục cột khi rollback. Các giá trị quyền đã bị xóa vẫn để trống.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('work_permissions')->nullable()->after('is_active');
        });
    }
};
