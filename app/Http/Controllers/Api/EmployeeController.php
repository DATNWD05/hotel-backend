<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreEmployeeRequest;
use App\Http\Requests\Employees\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    public function index()
    {
        $data = Employee::with(['department', 'role', 'user'])->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'total_pages' => $data->lastPage(),
                'total_items' => $data->total(),
                'per_page' => $data->perPage(),
            ],
        ]);
    }


    public function store(StoreEmployeeRequest $request)
    {
        try {
            $employee = Employee::create($request->validated());
            return response()->json([
                'status' => 'success',
                'data' => $employee->load('department'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Lỗi hệ thống khi cập nhật nhân viên: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi hệ thống.',
            ], 500);
        }
    }

    public function show(Employee $employee)
    {
        return response()->json([
            'status' => 'success',
            'data' => $employee->load('department'),
        ]);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        try {
            $employee->update($request->validated());
            return response()->json([
                'status' => 'success',
                'data' => $employee->load('department'),
            ]);
        } catch (\Exception $e) {
            Log::error('Lỗi hệ thống khi cập nhật nhân viên: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi hệ thống.',
            ], 500);
        }
    }

    public function destroy(Employee $employee)
    {
        try {
            if (Department::where('manager_id', $employee->id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Không thể xóa nhân viên đang là quản lý phòng ban.',
                ], 400);
            }
            $employee->delete();
            return response()->json([], 204);
        } catch (\Exception $e) {
            Log::error('Lỗi khi xóa nhân viên: ' . $e->getMessage() . ' - ID: ' . $employee->id);
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi hệ thống.',
            ], 500);
        }
    }
}
