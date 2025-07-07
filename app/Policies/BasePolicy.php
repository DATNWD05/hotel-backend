<?php

namespace App\Policies;

use App\Models\User;

class BasePolicy
{
    /**
     * Owner toàn quyền
     */
    public function before(User $user, $ability)
    {
        if ($user->role_id === 1) { // Chủ hệ thống hoặc role đặc biệt
            return true;
        }
    }

    /**
     * Xem danh sách
     */
    public function viewAny(User $user)
    {
        return $this->check($user, 'view');
    }

    /**
     * Xem chi tiết
     */
    public function view(User $user)
    {
        return $this->check($user, 'view');
    }

    /**
     * Tạo mới
     */
    public function create(User $user)
    {
        return $this->check($user, 'create');
    }

    /**
     * Cập nhật
     */
    public function update(User $user)
    {
        return $this->check($user, 'edit');
    }

    /**
     * Xóa
     */
    public function delete(User $user)
    {
        return $this->check($user, 'delete');
    }

    /**
     * Hàm kiểm tra quyền động theo tên model
     */
    protected function check(User $user, string $action): bool
    {
        $model = strtolower(str_replace('Policy', '', class_basename(static::class)));
        $permission = "{$action}_{$model}s";

        $hasPermission = $user->role
            && $user->role->permissions
            && $user->role->permissions->contains('name', $permission);

        if (!$hasPermission) {
            if (!request()->expectsJson()) {
                session()->flash('error', 'Bạn không có quyền truy cập chức năng này.');
            }
        }

        return $hasPermission;
    }
}
