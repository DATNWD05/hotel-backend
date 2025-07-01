<?php

namespace App\Http\Controllers\Api;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RoleController extends Controller
{
    use AuthorizesRequests;

    /**
     * Lấy danh sách tất cả các vai trò
     */
    public function index()
    {
        // Kiểm tra permission viewAny (view_roles)
        $this->authorize('viewAny', Role::class);

        $roles = Role::all();

        return response()->json([
            'message' => 'Danh sách vai trò',
            'roles'   => $roles,
            'status'  => 200
        ]);
    }

    /**
     * Tạo vai trò mới và gán quyền nếu có
     */
    public function store(Request $request)
    {
        $this->authorize('create', Role::class);

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
                'status'  => 422
            ]);
        }

        $role = Role::create([
            'name'        => trim($request->name),
            'description' => $request->description,
        ]);
        if (strtolower($role->name) === 'owner') {
            $role->permissions()->sync(Permission::pluck('id')->toArray());
        } elseif ($request->has('permissions')) {
            $role->permissions()->sync(array_unique($request->permissions));
        }

        return response()->json([
            'message' => 'Tạo vai trò thành công',
            'role'    => $role->load('permissions'),
            'status'  => 201
        ]);
    }

    /**
     * Lấy chi tiết 1 vai trò kèm quyền
     */
    public function show(Role $role)
    {
        $this->authorize('view', $role);

        return response()->json([
            'message' => 'Chi tiết vai trò',
            'role'    => $role->load('permissions'),
            'status'  => 200
        ]);
    }

    /**
     * Cập nhật thông tin và quyền của vai trò
     */
    public function update(Request $request, Role $role)
    {
        $this->authorize('update', $role);

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
                'status'  => 422
            ]);
        }

        $role->update([
            'name'        => trim($request->name),
            'description' => $request->description,
        ]);
        if (strtolower($role->name) === 'owner') {
            $role->permissions()->sync(Permission::pluck('id')->toArray());
        } elseif ($request->has('permissions')) {
            $role->permissions()->sync(array_unique($request->permissions));
        }

        return response()->json([
            'message' => 'Cập nhật vai trò thành công',
            'role'    => $role->load('permissions'),
            'status'  => 200
        ]);
    }

    /**
     * Xóa vai trò
     */
    public function destroy(Role $role)
    {
        $this->authorize('delete', $role);

        $role->delete();

        return response()->json([
            'message' => 'Xóa vai trò thành công',
            'status'  => 200
        ]);
    }

    /**
     * Gán thêm quyền cho vai trò mà không ghi đè quyền cũ
     */
    public function assignPermissions(Request $request, Role $role)
    {
        $this->authorize('assign', $role);

        $validator = Validator::make($request->all(), [
            'permissions'   => 'required|array',
            'permissions.*' => 'integer|exists:permissions,id'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
                'status'  => 422
            ]);
        }

        $role->permissions()->syncWithoutDetaching(array_unique($request->permissions));

        return response()->json([
            'message' => 'Đã gán thêm quyền thành công',
            'role'    => $role->load('permissions'),
            'status'  => 200
        ]);
    }

    /**
     * Gỡ bớt quyền khỏi vai trò
     */
    public function removePermissions(Request $request, Role $role)
    {
        $this->authorize('remove', $role);

        $validator = Validator::make($request->all(), [
            'permissions'   => 'required|array',
            'permissions.*' => 'integer|exists:permissions,id'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ',
                'errors'  => $validator->errors(),
                'status'  => 422
            ]);
        }

        $role->permissions()->detach(array_unique($request->permissions));

        return response()->json([
            'message' => 'Đã gỡ quyền thành công',
            'role'    => $role->load('permissions'),
            'status'  => 200
        ]);
    }
}
