<?php

namespace App\Policies;

class RolePolicy extends BasePolicy
{
    public function assign($user, $role)
    {
        return $this->check($user, 'assign_permissions');
    }

    public function remove($user, $role)
    {
        return $this->check($user, 'remove_permissions');
    }
}
