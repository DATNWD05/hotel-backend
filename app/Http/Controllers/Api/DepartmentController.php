<?php

namespace App\Http\Controllers\Api;

use App\Models\Department;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\Departments\StoreDepartmentRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Requests\Departments\UpdateDepartmentRequest;

class DepartmentController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Department::class, 'department');
    }
    public function index()
    {
        $data = Department::with('manager')->paginate(10);

        return response()->json([
            'status' => 'success',
            'data'   => $data->map(function ($department) {
                return [
                    'id'            => $department->id,
                    'name'          => $department->name,
                    'manager_name'  => $department->manager?->name, // Null-safe nếu không có manager
                    'created_at'    => $department->created_at,
                    'updated_at'    => $department->updated_at,
                ];
            }),
            'meta'   => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ]);
    }


    public function show(Department $department)
    {
        return response()->json([
            'status' => 'success',
            'data'   => $department->load('manager'),
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
            $department->load('manager');

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'id'            => $department->id,
                    'name'          => $department->name,
                    'manager_id'    => $department->manager_id,
                    'manager_name'  => $department->manager?->name,
                    'created_at'    => $department->created_at,
                    'updated_at'    => $department->updated_at,
                ],
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
