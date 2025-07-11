<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Role;
use App\Policies\BasePolicy;

class RolePolicy extends BasePolicy
{
    /**
     * Gán thêm permission cho vai trò
     */
    public function assign(User $user, Role $role): bool
    {
        return $this->check($user, 'assign_permissions');
    }

    /**
     * Gỡ permission khỏi vai trò
     */
    public function remove(User $user, Role $role): bool
    {
        return $this->check($user, 'remove_permissions');
    }

    /**
     * Cho phép user xem chính role của họ
     */    public function view(User $user, $model = null): bool
    {
        // Nếu đúng instance Role và id trùng với role_id của user
        if ($model instanceof Role && $model->id === $user->role_id) {
            return true;
        }

        // Fallback về BasePolicy::view(User, $model)
        return parent::view($user, $model);
    }
}
