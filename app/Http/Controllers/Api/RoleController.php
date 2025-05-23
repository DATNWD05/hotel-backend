<?php

namespace App\Http\Controllers\Api;

use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    // Lấy danh sách tất cả các role
    public function index()
    {
        return response()->json([
            'message' => 'Danh sách vai trò',
            'roles' => Role::all(),
        ]);
    }

    // Tạo mới một role
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json(
            [
                'message' => 'Tạo vai trò thành công',
                'role' => $role,
                'status' => 201
            ]
        );
    }

    // Lấy chi tiết một role
    public function show(Role $role)
    {
        return response()->json($role);
    }

    // Cập nhật một role
    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json($role);
    }

    // Xóa một role
    public function destroy(Role $role)
    {
        $role->delete();
        return response()->json(
            [
                'message' => 'Xóa vai trò thành công'
            ]
        );
    }
}
