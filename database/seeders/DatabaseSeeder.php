<?php

namespace Database\Seeders;

use App\Domain\Staff\Enums\UserRole;
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
    }
}