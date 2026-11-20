<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn truy cập phân hệ khi tài khoản chưa đăng nhập, đã bị khóa hoặc tên phân hệ không hợp lệ.
 */
class RequireWorkPermission
{
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $user = $request->user();
        abort_unless($user && $user->is_active && $user->canAccessWorkArea($area), 403);

        return $next($request);
    }
}
