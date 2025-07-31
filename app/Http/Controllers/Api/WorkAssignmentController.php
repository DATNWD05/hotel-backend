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

    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(WorkAssignment::class, 'work_assignments');
    }

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

    // Phân công nhiều ca làm việc
    public function store(Request $request)
    {
        $request->validate([
            'assignments' => 'required|array|min:1',
            'assignments.*.employee_id' => 'required|exists:employees,id',
            'assignments.*.work_date' => 'required|date',
            'assignments.*.shift_ids' => 'nullable|array',
            'assignments.*.shift_ids.*' => 'exists:shifts,id'
        ]);

        $created = [];
        $deleted = [];
        $skipped = [];

        $today = now()->format('Y-m-d');

        foreach ($request->assignments as $assignment) {
            $employeeId = $assignment['employee_id'];
            $date = $assignment['work_date'];
            $newShiftIds = $assignment['shift_ids'] ?? [];

            // Không cho phép phân công ngược ngày
            if ($date < $today) {
                $skipped[] = [
                    'employee_id' => $employeeId,
                    'work_date' => $date,
                    'reason' => 'Không thể phân công cho ngày đã qua'
                ];
                continue;
            }

            // Giới hạn tối đa 2 ca/ngày
            if (count($newShiftIds) > 2) {
                $skipped[] = [
                    'employee_id' => $employeeId,
                    'work_date' => $date,
                    'reason' => 'Vượt quá giới hạn 2 ca/ngày'
                ];
                continue;
            }

            // Lấy các ca đã phân cho nhân viên trong ngày đó
            $existingAssignments = WorkAssignment::where('employee_id', $employeeId)
                ->where('work_date', $date)
                ->get();

            $existingShiftIds = $existingAssignments->pluck('shift_id')->toArray();

            // Xóa những ca không còn tồn tại
            foreach ($existingAssignments as $existing) {
                if (!in_array($existing->shift_id, $newShiftIds)) {
                    $existing->delete();
                    $deleted[] = [
                        'employee_id' => $employeeId,
                        'work_date' => $date,
                        'shift_id' => $existing->shift_id
                    ];
                }
            }

            // Tạo mới các ca chưa có
            foreach ($newShiftIds as $shiftId) {
                if (!in_array($shiftId, $existingShiftIds)) {
                    $created[] = WorkAssignment::create([
                        'employee_id' => $employeeId,
                        'work_date' => $date,
                        'shift_id' => $shiftId
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Phân công nhiều ca đã được xử lý.',
            'created_count' => count($created),
            'deleted_count' => count($deleted),
            'skipped_count' => count($skipped),
            'data' => [
                'created' => $created,
                'deleted' => $deleted,
                'skipped' => $skipped
            ]
        ]);
    }

    // Cập nhật phân công
    // public function update(Request $request, WorkAssignment $workAssignment)
    // {
    //     $messages = [
    //         'employee_id.required' => 'Vui lòng chọn nhân viên cần phân công.',
    //         'employee_id.exists' => 'Nhân viên không tồn tại trong hệ thống.',
    //         'shift_id.required' => 'Vui lòng chọn ca làm việc.',
    //         'shift_id.exists' => 'Ca làm việc không tồn tại.',
    //         'work_date.required' => 'Vui lòng chọn ngày làm việc.',
    //         'work_date.date' => 'Ngày làm việc không hợp lệ. Vui lòng nhập đúng định dạng YYYY-MM-DD.',
    //     ];

    //     $validator = Validator::make($request->all(), [
    //         'employee_id' => 'required|exists:employees,id',
    //         'shift_id' => 'required|exists:shifts,id',
    //         'work_date' => 'required|date',
    //     ], $messages);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Dữ liệu không hợp lệ khi cập nhật phân công.',
    //             'errors' => $validator->errors()
    //         ], 422);
    //     }

    //     // Kiểm tra trùng phân công (nếu có phân công khác giống hệt)
    //     $exists = WorkAssignment::where('employee_id', $request->employee_id)
    //         ->where('shift_id', $request->shift_id)
    //         ->where('work_date', $request->work_date)
    //         ->where('id', '!=', $workAssignment->id)
    //         ->exists();

    //     if ($exists) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Phân công này đã tồn tại cho nhân viên trong ca và ngày đã chọn.',
    //         ], 409);
    //     }

    //     // Cập nhật
    //     $workAssignment->update($validator->validated());

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Cập nhật phân công thành công.',
    //         'data' => $workAssignment,
    //     ]);
    // }

    // Xoá phân công
    // public function destroy(WorkAssignment $workAssignment)
    // {
    //     if (!$workAssignment) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Không tìm thấy phân công cần xoá.',
    //         ], 404);
    //     }

    //     $workAssignment->delete();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Xoá phân công thành công.',
    //     ]);
    // }

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
