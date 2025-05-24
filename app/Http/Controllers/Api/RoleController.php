<?php

namespace App\Http\Controllers\Api;

use App\Models\Role;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Exception;

class RoleController extends Controller
{
    // Lấy danh sách tất cả các role
    public function index()
    {
        try {
            $roles = Role::all();

            return response()->json([
                'message' => 'Danh sách vai trò',
                'roles' => $roles,
                'status' => 200
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi lấy danh sách vai trò',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }


    public function store(Request $request)
    {
        try {
            // Validate với thông báo lỗi chi tiết
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:255',
            ], [
                'name.required' => 'Tên vai trò là bắt buộc.',
                'name.string' => 'Tên vai trò phải là chuỗi.',
                'name.max' => 'Tên vai trò không được vượt quá 255 ký tự.',
                'description.string' => 'Mô tả phải là chuỗi.',
                'description.max' => 'Mô tả không được vượt quá 255 ký tự.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors(),
                    'status' => 422
                ], 422);
            }

            // Nếu hợp lệ thì tạo vai trò
            $role = Role::create([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            return response()->json([
                'message' => 'Tạo vai trò thành công',
                'role' => $role,
                'status' => 201
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi tạo vai trò',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    // Lấy chi tiết một role
    public function show($id)
    {
        try {
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'message' => 'Không tìm thấy vai trò với ID: ' . $id,
                    'status' => 404
                ], 404);
            }

            return response()->json([
                'message' => 'Chi tiết vai trò',
                'role' => $role,
                'status' => 200
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi lấy chi tiết vai trò',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Tìm vai trò theo ID
            $role = Role::find($id);

            if (!$role) {
                return response()->json([
                    'message' => 'Không tìm thấy vai trò với ID: ' . $id,
                    'status' => 404
                ], 404);
            }

            // Validate với thông báo lỗi tùy chỉnh
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:255',
            ], [
                'name.required' => 'Tên vai trò là bắt buộc.',
                'name.string' => 'Tên vai trò phải là chuỗi.',
                'name.max' => 'Tên vai trò không được vượt quá 255 ký tự.',
                'description.string' => 'Mô tả phải là chuỗi.',
                'description.max' => 'Mô tả không được vượt quá 255 ký tự.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors(),
                    'status' => 422
                ], 422);
            }

            // Cập nhật vai trò
            $role->update([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            return response()->json([
                'message' => 'Cập nhật vai trò thành công',
                'role' => $role,
                'status' => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật vai trò',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function destroy(Role $role)
    {
        try {
            $role->delete();

            return response()->json([
                'message' => 'Xóa vai trò thành công',
                'status' => 200
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi xóa vai trò',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }
}
