<?php

namespace App\Models;

use App\Enums\Staff\UserRole;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    public const WORK_PERMISSIONS = [
        'customers' => 'Khách hàng',
        'leads' => 'Lead',
        'opportunities' => 'Cơ hội',
        'tasks' => 'Nhiệm vụ',
        'calendar' => 'Lịch trình',
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    public static function normalizeLoginEmail(string $email): string
    {
        return mb_strtolower(trim($email), 'UTF-8');
    }

    protected function email(): Attribute
    {
        // Mọi lượt ghi email qua model dùng cùng giá trị để chỉ mục UNIQUE xử lý cả ghi đồng thời.
        return Attribute::make(set: self::normalizeLoginEmail(...));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role' => UserRole::class,
        ];
    }

    public function isManager(): bool
    {
        return $this->role->seesAllCustomers();
    }

    /**
     * Kiểm tra tên phân hệ trong danh sách chung. Middleware kiểm tra tài khoản đang hoạt động.
     */
    public function canAccessWorkArea(string $area): bool
    {
        return array_key_exists($area, self::WORK_PERMISSIONS);
    }
}
