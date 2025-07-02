<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Permission;


class Role extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Get the users associated with the role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // Quan hệ nhiều-nhiều với Permission
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }
    public function syncPermissions($permissions)
    {
        $this->permissions()->sync(array_unique($permissions));
    }
}
