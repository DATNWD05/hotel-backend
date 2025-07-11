<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WorkAssignment;
use Illuminate\Support\Facades\Validator;
use App\Imports\WorkAssignmentImport;
use Maatwebsite\Excel\Facades\Excel;

class WorkAssignmentController extends Controller
{
    // Danh sách phân công
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $assignments = WorkAssignment::with(['employee', 'shift'])
            ->orderByDesc('work_date')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Danh sách phân công ca làm việc',
            'data' => $assignments
        ]);
    }


    public function store(Request $request)
    {
        $messages = [
            'employee_id.required' => ' Vui lòng chọn nhân viên cần phân công.',
            'employee_id.exists' => ' Nhân viên được chọn không tồn tại trong hệ thống.',
            'shift_id.required' => ' Vui lòng chọn ca làm việc.',
            'shift_id.exists' => ' Ca làm việc được chọn không tồn tại.',
            'work_date.required' => ' Vui lòng chọn ngày làm việc.',
            'work_date.date' => ' Ngày làm việc không hợp lệ. Vui lòng chọn đúng định dạng ngày (VD: 2025-07-11).',
        ];

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'work_date' => 'required|date',
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => ' Dữ liệu không hợp lệ. Vui lòng kiểm tra lại các trường nhập.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Kiểm tra trùng lặp
        $exists = WorkAssignment::where('employee_id', $request->employee_id)
            ->where('work_date', $request->work_date)
            ->first();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => ' Nhân viên này đã được phân công trong ngày ' . $request->work_date . '.',
            ], 409);
        }

        // Tạo mới
        $assignment = WorkAssignment::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => ' Phân công thành công cho nhân viên ID: ' . $assignment->employee_id . ' vào ngày ' . $assignment->work_date,
            'data' => $assignment,
        ]);
    }

    // Cập nhật phân công
    public function update(Request $request, WorkAssignment $workAssignment)
    {
        $messages = [
            'employee_id.required' => ' Vui lòng chọn nhân viên cần phân công.',
            'employee_id.exists' => 'Nhân viên không tồn tại trong hệ thống.',
            'shift_id.required' => ' Vui lòng chọn ca làm việc.',
            'shift_id.exists' => ' Ca làm việc không tồn tại.',
            'work_date.required' => ' Vui lòng chọn ngày làm việc.',
            'work_date.date' => ' Ngày làm việc không hợp lệ. Vui lòng nhập đúng định dạng YYYY-MM-DD.',
        ];

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'work_date' => 'required|date',
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => ' Dữ liệu không hợp lệ khi cập nhật phân công.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Kiểm tra trùng lặp
        $exists = WorkAssignment::where('employee_id', $request->employee_id)
            ->where('work_date', $request->work_date)
            ->where('id', '!=', $workAssignment->id)
            ->first();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => ' Nhân viên này đã có phân công khác trong ngày ' . $request->work_date . '.',
            ], 409);
        }

        // Cập nhật phân công
        $workAssignment->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => ' Cập nhật phân công thành công.',
            'data' => $workAssignment,
        ]);
    }

    // Xoá phân công
    public function destroy(WorkAssignment $workAssignment)
    {
        if (!$workAssignment) {
            return response()->json([
                'success' => false,
                'message' => ' Không tìm thấy phân công cần xoá.',
            ], 404);
        }

        $workAssignment->delete();

        return response()->json([
            'success' => true,
            'message' => ' Xoá phân công thành công.',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls'
        ]);

        try {
            Excel::import(new WorkAssignmentImport, $request->file('file'));

            return response()->json([
                'success' => true,
                'message' => '📥 Import phân công thành công!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '❌ Lỗi khi import: ' . $e->getMessage()
            ], 500);
        }
    }
}
