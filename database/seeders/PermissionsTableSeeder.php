<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Users & profile
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',
            'view_profile',

            // Roles & permissions (admin-only group in routes)
            'view_roles',
            'create_roles',
            'edit_roles',
            'delete_roles',
            'assign_permissions',
            'remove_permissions',
            'view_permissions',

            // Employees & Departments
            'view_employees',
            'create_employees',
            'edit_employees',
            'delete_employees',

            'view_departments',
            'create_departments',
            'edit_departments',
            'delete_departments',

            // Room Types
            'view_room_types',
            'create_room_types',
            'edit_room_types',
            'delete_room_types',
            'sync_amenities_room_types',

            // Rooms (+ soft delete helpers)
            'view_rooms',
            'view_trashed_rooms',
            'create_rooms',
            'edit_rooms',
            'delete_rooms',
            'restore_rooms',
            'force_delete_rooms',

            // Promotions
            'view_promotions',
            'create_promotions',
            'edit_promotions',
            'delete_promotions',
            'apply_promotions',

            // Service Categories
            'view_service_categories',
            'create_service_categories',
            'edit_service_categories',
            'delete_service_categories',

            // Services
            'view_services',
            'create_services',
            'edit_services',
            'delete_services',

            // Amenity Categories (with soft-delete management)
            'view_amenity_categories',
            'view_trashed_amenity_categories',
            'create_amenity_categories',
            'edit_amenity_categories',
            'delete_amenity_categories',
            'restore_amenity_categories',
            'force_delete_amenity_categories',

            // Amenities
            'view_amenities',
            'create_amenities',
            'edit_amenities',
            'delete_amenities',

            // Bookings & flows
            'view_bookings',
            'create_bookings',
            'edit_bookings',
            'cancel_bookings',
            'add_services_bookings',
            'remove_services_bookings',
            'checkin_bookings',
            'checkout_bookings',
            'pay_cash_bookings',
            'pay_deposit_bookings',
            'view_booking_detail',
            'view_checkin_info_bookings',
            'validate_rooms',
            'get_available_rooms',

            // Customers
            'view_customers',
'show_customers',
            'create_customers',
            'edit_customers',
            'check_cccd_customers',

            // Invoices
            'view_invoices',
            'print_invoices',

            // Statistics (match current routes)
            'view_revenue_table_statistics',
            'view_booking_service_table_statistics',
            'view_summary_dashboard_statistics',

            // Payrolls
            'view_payrolls',
            'generate_payrolls',
            'show_payrolls',
            'export_pdf_payrolls',
            'export_excel_payrolls',

            // Work Assignments
            'view_work_assignments',
            'create_work_assignments',
            'import_work_assignments',

            // Shifts
            'view_shifts',
            'create_shifts',
            'edit_shifts',
            'delete_shifts',
            'show_shifts',

            // Overtime Requests
            'view_overtime_requests',
            'create_overtime_requests',
            'delete_overtime_requests_by_date',

            // Attendance & Face enrollment
            'view_attendances',
            'create_face_attendance',
            'upload_employee_faces',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
    }
}