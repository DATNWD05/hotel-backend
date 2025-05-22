<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Log;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  mixed ...$roles  // Vai trò được truyền vào từ route (admin, manager, ...)
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        // ⚠️ Chưa đăng nhập
        if (!$user) {
            return response()->json([
                'message' => 'Bạn chưa đăng nhập.'
            ], 401);
        }

        // ⚠️ Không có vai trò gán
        if (!$user->role) {
            return response()->json([
                'message' => 'Người dùng chưa được gán vai trò.'
            ], 403);
        }

        // ⚠️ Vai trò không hợp lệ
        if (!in_array($user->role->name, $roles)) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập.',
                'vai_tro_hien_tai' => $user->role->name,
                'yeu_cau' => $roles,
            ], 403);
        }

        return $next($request);
    }
}
