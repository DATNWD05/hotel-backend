<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WorkAssignment;
use Illuminate\Support\Facades\Validator;
use App\Imports\WorkAssignmentImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class WorkAssignmentController extends Controller
{

    // use AuthorizesRequests;

    // public function __construct()
    // {
    //     $this->authorizeResource(WorkAssignment::class, 'work_assignments');
    // }

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
        $request->validate([
            'employee_ids' => 'required|array|min:1',
            'work_dates' => 'required|array|min:1',
            'shift_id' => 'nullable|exists:shifts,id',
        ]);

        $created = [];
        $updated = [];
        $deleted = [];

        foreach ($request->employee_ids as $employeeId) {
            foreach ($request->work_dates as $date) {
                $assignment = WorkAssignment::where('employee_id', $employeeId)
                    ->where('work_date', $date)
                    ->first();

                $shiftId = $request->shift_id ?? null;

                // Nếu không chọn ca (shift_id null hoặc rỗng) thì xóa phân công nếu có
                if (empty($shiftId)) {
                    if ($assignment) {
                        $assignment->delete();
                        $deleted[] = [
                            'employee_id' => $employeeId,
                            'work_date' => $date,
                            'action' => 'deleted',
                        ];
                    }
                    continue;
                }

                // Nếu đã có phân công, kiểm tra và cập nhật ca nếu khác
                if ($assignment) {
                    if ($assignment->shift_id != $shiftId) {
                        $assignment->shift_id = $shiftId;
                        $assignment->save();

                        $updated[] = [
                            'employee_id' => $employeeId,
                            'work_date' => $date,
                            'new_shift_id' => $shiftId,
                        ];
                    }
                } else {
                    // Nếu chưa có thì tạo mới phân công
                    $created[] = WorkAssignment::create([
                        'employee_id' => $employeeId,
                        'shift_id' => $shiftId,
                        'work_date' => $date,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Phân công ca làm đã được xử lý.',
            'created_count' => count($created),
            'updated_count' => count($updated),
            'deleted_count' => count($deleted),
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'deleted' => $deleted,
            ]
        ]);
    }

    // Cập nhật phân công
    public function update(Request $request, WorkAssignment $workAssignment)
    {
        $messages = [
            'employee_id.required' => 'Vui lòng chọn nhân viên cần phân công.',
            'employee_id.exists' => 'Nhân viên không tồn tại trong hệ thống.',
            'shift_id.required' => 'Vui lòng chọn ca làm việc.',
            'shift_id.exists' => 'Ca làm việc không tồn tại.',
            'work_date.required' => 'Vui lòng chọn ngày làm việc.',
            'work_date.date' => 'Ngày làm việc không hợp lệ. Vui lòng nhập đúng định dạng YYYY-MM-DD.',
        ];

        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'work_date' => 'required|date',
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ khi cập nhật phân công.',
                'errors' => $validator->errors()
            ], 422);
        }

        // Kiểm tra trùng phân công (nếu có phân công khác giống hệt)
        $exists = WorkAssignment::where('employee_id', $request->employee_id)
            ->where('shift_id', $request->shift_id)
            ->where('work_date', $request->work_date)
            ->where('id', '!=', $workAssignment->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Phân công này đã tồn tại cho nhân viên trong ca và ngày đã chọn.',
            ], 409);
        }

        // Cập nhật
        $workAssignment->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật phân công thành công.',
            'data' => $workAssignment,
        ]);
    }

    // Xoá phân công
    public function destroy(WorkAssignment $workAssignment)
    {
        if (!$workAssignment) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy phân công cần xoá.',
            ], 404);
        }

        $workAssignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Xoá phân công thành công.',
        ]);
    }

    public function import(Request $request)
    {
        if (!$request->hasFile('file')) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng chọn file Excel.'
            ], 400);
        }

        try {
            Excel::import(new WorkAssignmentImport, $request->file('file'));
            return response()->json([
                'success' => true,
                'message' => 'Import phân công thành công!'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import: ' . $e->getMessage()
            ], 500);
        }
    }
}
