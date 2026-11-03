<?php

namespace App\Domain\Staff\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Sales = 'sales';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Quản trị',
            self::Manager => 'Quản lý',
            self::Sales => 'Nhân viên kinh doanh',
        };
    }

    public function seesAllCustomers(): bool
    {
        return $this !== self::Sales;
    }
}