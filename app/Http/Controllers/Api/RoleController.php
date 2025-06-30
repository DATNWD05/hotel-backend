<?php

namespace App\Http\Controllers\Api;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Exception;

class RoleController extends Controller
{
    /**
     * Lấy danh sách tất cả các vai trò
     */
    public function index()
    {
        try {
            $roles = Role::all();

            return response()->json([
                'message' => 'Danh sách vai trò',
                'roles' => $roles,
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy danh sách vai trò',
                'error' => $e->getMessage(),
                'status' => 500
            ]);
        }
    }

    /**
     * Tạo vai trò mới và gán quyền nếu có
     */
    public function store(Request $request)
    {
        try {
            // Validate đầu vào
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:255',
                'permissions' => 'nullable|array',
                'permissions.*' => 'integer|exists:permissions,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors(),
                    'status' => 422
                ]);
            }

            // Tạo vai trò mới
            $role = Role::create([
                'name' => trim($request->name),
                'description' => $request->description,
            ]);

            // Nếu là owner thì gán toàn bộ quyền
            if (strtolower(trim($role->name)) === 'owner') {
                $allPermissions = Permission::pluck('id')->unique()->toArray();
                $role->permissions()->sync($allPermissions);
            }
            // Nếu là vai trò thường, gán theo request (nếu có)
            elseif ($request->has('permissions')) {
                $role->permissions()->sync(array_unique($request->permissions));
            }

            return response()->json([
                'message' => 'Tạo vai trò thành công',
                'role' => $role->load('permissions'),
                'status' => 201
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi tạo vai trò',
                'error' => $e->getMessage(),
                'status' => 500
            ]);
        }
    }

    /**
     * Lấy chi tiết 1 vai trò kèm quyền
     */
    public function show($id)
    {
        try {
            // Gọi thêm quan hệ permissions
            $role = Role::with('permissions')->find($id);

            if (!$role) {
                return response()->json([
                    'message' => 'Không tìm thấy vai trò',
                    'status' => 404
                ]);
            }

            return response()->json([
                'message' => 'Chi tiết vai trò',
                'role' => $role,
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi lấy chi tiết vai trò',
                'error' => $e->getMessage(),
                'status' => 500
            ]);
        }
    }

    /**
     * Cập nhật thông tin và quyền của vai trò
     */
    public function update(Request $request, $id)
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'message' => 'Không tìm thấy vai trò',
                    'status' => 404
                ]);
            }

            // Validate dữ liệu
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors(),
                    'status' => 422
                ]);
            }

            // Cập nhật vai trò
            $role->update([
                'name' => trim($request->name),
                'description' => $request->description,
            ]);

            // Nếu là owner thì gán toàn bộ quyền
            if (strtolower(trim($role->name)) === 'owner') {
                $allPermissions = Permission::pluck('id')->unique()->toArray();
                $role->permissions()->sync($allPermissions);
            }
            // Gán lại quyền theo request nếu có
            elseif ($request->has('permissions')) {
                $role->permissions()->sync(array_unique($request->permissions));
            }

            return response()->json([
                'message' => 'Cập nhật vai trò thành công',
                'role' => $role->load('permissions'),
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi cập nhật vai trò',
                'error' => $e->getMessage(),
                'status' => 500
            ]);
        }
    }

    /**
     * Xóa vai trò
     */
    public function destroy($id)
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'message' => 'Không tìm thấy vai trò',
                    'status' => 404
                ]);
            }

            $role->delete();

            return response()->json([
                'message' => 'Xóa vai trò thành công',
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi xóa vai trò',
                'error' => $e->getMessage(),
                'status' => 500
            ]);
        }
    }

    /**
     * Gán thêm quyền cho vai trò mà không ghi đè quyền cũ
     */
    public function assignPermissions(Request $request, $id)
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'message' => 'Không tìm thấy vai trò',
                    'status' => 404
                ]);
            }

            $validator = Validator::make($request->all(), [
                'permissions' => 'required|array',
                'permissions.*' => 'integer|exists:permissions,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors(),
                    'status' => 422
                ]);
            }

            // Gán thêm mà không xóa quyền cũ
            $role->permissions()->syncWithoutDetaching(array_unique($request->permissions));

            return response()->json([
                'message' => 'Đã gán thêm quyền thành công',
                'role' => $role->load('permissions'),
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi gán quyền',
                'error' => $e->getMessage(),
                'status' => 500
            ]);
        }
    }

    /**
     * Gỡ bớt quyền khỏi vai trò
     */
    public function removePermissions(Request $request, $id)
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'message' => 'Không tìm thấy vai trò',
                    'status' => 404
                ]);
            }

            $validator = Validator::make($request->all(), [
                'permissions' => 'required|array',
                'permissions.*' => 'integer|exists:permissions,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors(),
                    'status' => 422
                ]);
            }

            $role->permissions()->detach(array_unique($request->permissions));

            return response()->json([
                'message' => 'Đã gỡ quyền thành công',
                'role' => $role->load('permissions'),
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi gỡ quyền',
                'error' => $e->getMessage(),
                'status' => 500
            ]);
        }
    }
    public function syncPermissions(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $validator->errors(),
            ], 422);
        }

        $role->syncPermissions($request->permissions);

        return response()->json([
            'message' => 'Đã cập nhật quyền cho vai trò',
            'role' => $role->load('permissions')
        ]);
    }
}
