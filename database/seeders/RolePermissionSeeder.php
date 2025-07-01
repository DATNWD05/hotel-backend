<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'manage_roles' => 'Quản lý vai trò & phân quyền',
            'manage_users' => 'Quản lý tài khoản người dùng',
            'manage_employees' => 'Quản lý nhân viên',
            'manage_departments' => 'Quản lý phòng ban',
            'manage_rooms' => 'Quản lý phòng',
            'manage_room_types' => 'Quản lý loại phòng',
            'manage_promotions' => 'Quản lý khuyến mãi',
            'manage_services' => 'Quản lý dịch vụ',
            'manage_amenities' => 'Quản lý tiện nghi',
            'manage_bookings' => 'Quản lý đặt phòng',
            'manage_invoices' => 'Quản lý hóa đơn',
            'manage_statistics' => 'Xem thống kê',
            'manage_customers' => 'Quản lý khách hàng',
        ];

        foreach ($permissions as $key => $desc) {
            Permission::updateOrCreate(['name' => $key], ['description' => $desc]);
        }

        $roles = [
            'Owner'  => ['*'],
            'Admin'  => array_keys($permissions),
            'Lễ Tân' => ['manage_bookings', 'manage_customers'],
        ];

        foreach ($roles as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            if (in_array('*', $perms)) {
                $role->permissions()->sync(Permission::pluck('id'));
            } else {
                $role->permissions()->sync(Permission::whereIn('name', $perms)->pluck('id'));
            }
        }
    }
}
