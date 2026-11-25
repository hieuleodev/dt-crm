<?php

namespace Database\Seeders;

use App\Enums\Staff\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@trangthietbi.vn'],
            [
                'name' => 'Quản trị hệ thống',
                'password' => 'Admin@12345',
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        // Seed tài khoản Sales theo email để có người nhận việc. Chạy lại sẽ cập nhật dữ liệu mẫu.
        User::updateOrCreate(
            ['email' => 'sale1@trangthietbi.vn'],
            [
                'name' => 'Nguyễn Văn A',
                'password' => 'Sale@12345',
                'role' => UserRole::Sales,
                'is_active' => true,
            ],
        );

        User::updateOrCreate(
            ['email' => 'sale3@trangthietbi.vn'],
            [
                'name' => 'Lê Hoàng Nam',
                'password' => 'Sale@12345',
                'role' => UserRole::Sales,
                'is_active' => true,
            ],
        );
    }
}
