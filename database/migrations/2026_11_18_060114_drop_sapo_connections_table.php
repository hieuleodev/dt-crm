<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Giữ nguyên bảng sapo_connections để OAuth tiếp tục dùng token đã lưu.
     */
    public function up(): void
    {
        //
    }

    /**
     * Giữ nguyên cấu trúc khi rollback migration này.
     */
    public function down(): void
    {
        //
    }
};
