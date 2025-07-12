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
        if ($user->role_id === 1) {
            return true;
        }
    }

    /**
     * Xem danh sách
     */
 public function viewAny(User $user, $model = null): bool
{
    return $this->check($user, 'view');
}

public function view(User $user, $model = null): bool
{
    return $this->check($user, 'view');
}
    /**
     * Tạo mới
     */
    public function create(User $user, $model = null): bool
    {
        return $this->check($user, 'create');
    }

    /**
     * Cập nhật
     */
    public function update(User $user, $model = null): bool
    {
        return $this->check($user, 'edit');
    }

    /**
     * Xóa
     */
    public function delete(User $user, $model = null): bool
    {
        return $this->check($user, 'delete');
    }

    /**
     * Kiểm tra permission động theo tên model
     */
    protected function check(User $user, string $action): bool
    {
        $modelName  = strtolower(str_replace('Policy', '', class_basename(static::class)));
        $permission = "{$action}_{$modelName}s";

        $has = $user->role
            && $user->role->permissions
            && $user->role->permissions->contains('name', $permission);

        if (! $has && ! request()->expectsJson()) {
            session()->flash('error', 'Bạn không có quyền truy cập chức năng này.');
        }

        return $has;
    }
}
