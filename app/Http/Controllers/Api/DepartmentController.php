<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\StoreDepartmentRequest;
use App\Http\Requests\Departments\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Support\Facades\Log;

class DepartmentController extends Controller
{
    public function index()
    {
        $data = Department::with('manager')->get();
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }


    public function store(StoreDepartmentRequest $request)
    {
        try {
            $department = Department::create($request->validated());
            return response()->json([
                'status' => 'success',
                'data' => $department->load('manager'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi không xác định.',
            ], 500);
        }
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        try {
            $department->update($request->validated());
            return response()->json([
                'status' => 'success',
                'data' => $department->load('manager'),
            ]);
        } catch (\Exception $e) {
            Log::error('Lỗi hệ thống khi cập nhật phòng ban: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi hệ thống.',
            ], 500);
        }
    }

    public function destroy(Department $department)
    {
        try {
            if ($department->employees()->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không thể xóa phòng ban vì vẫn còn nhân viên liên kết.',
                ], 400);
            }
            $department->delete();
            return response()->json([], 204); // 204 No Content, không cần body
        } catch (\Exception $e) {
            Log::error('Lỗi khi xóa phòng ban: ' . $e->getMessage() . ' - ID: ' . $department->id);
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi hệ thống.',
            ], 500);
        }
    }
}
