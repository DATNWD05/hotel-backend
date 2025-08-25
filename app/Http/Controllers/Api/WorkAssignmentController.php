<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WorkAssignment;
use Illuminate\Support\Facades\Validator;
use App\Imports\WorkAssignmentImport;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Employee;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Shift;
use Carbon\Carbon;

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
        // $perPage = $request->input('per_page', 10);
        $assignments = WorkAssignment::with(['employee.user.role', 'shift'])
            ->orderByDesc('work_date')
            ->get();

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

        $today = now('Asia/Ho_Chi_Minh')->format('Y-m-d');

        // Có thể đưa vào config('attendance.min_rest_hours', 11) cho linh hoạt
        $minRestHours = config('attendance.min_rest_hours', 11);

        foreach ($request->assignments as $assignment) {
            $employeeId  = $assignment['employee_id'];
            $date        = $assignment['work_date'];
            $newShiftIds = $assignment['shift_ids'] ?? [];

            // Kiểm tra trạng thái nhân viên
            $employee = \App\Models\Employee::find($employeeId);
            if (!$employee || strtolower($employee->status) !== 'active') {
                $skipped[] = [
                    'employee_id' => $employeeId,
                    'work_date'   => $date,
                    'reason'      => 'Nhân viên đã nghỉ việc hoặc không hoạt động',
                ];
                continue;
            }

            // Không cho phép phân công ngược ngày
            if ($date < $today) {
                $skipped[] = [
                    'employee_id' => $employeeId,
                    'work_date'   => $date,
                    'reason'      => 'Không thể phân công cho ngày đã qua',
                ];
                continue;
            }

            // Giới hạn tối đa 2 ca/ngày
            if (count($newShiftIds) > 2) {
                $skipped[] = [
                    'employee_id' => $employeeId,
                    'work_date'   => $date,
                    'reason'      => 'Vượt quá giới hạn 2 ca/ngày',
                ];
                continue;
            }

            // Lấy các ca đã phân trong ngày (để xóa những ca không còn và tránh tạo trùng)
            $existingAssignments = \App\Models\WorkAssignment::where('employee_id', $employeeId)
                ->where('work_date', $date)
                ->get();

            $existingShiftIds = $existingAssignments->pluck('shift_id')->toArray();

            // Xóa những ca không còn trong danh sách mới
            foreach ($existingAssignments as $existing) {
                if (!in_array($existing->shift_id, $newShiftIds)) {
                    $existing->delete();
                    $deleted[] = [
                        'employee_id' => $employeeId,
                        'work_date'   => $date,
                        'shift_id'    => $existing->shift_id,
                    ];
                }
            }

            // Chuẩn bị dữ liệu kiểm tra nghỉ tối thiểu
            $workDate = Carbon::parse($date, 'Asia/Ho_Chi_Minh');

            // Lấy các ca hôm trước
            $yesterdayAssignments = \App\Models\WorkAssignment::with('shift')
                ->where('employee_id', $employeeId)
                ->where('work_date', $workDate->copy()->subDay()->toDateString())
                ->get();

            // Tính end thực của ca hôm trước (xử lý ca qua đêm)
            $yesterdayEnds = [];
            foreach ($yesterdayAssignments as $ya) {
                $s = $ya->shift;
                if (!$s) continue;

                $ysStart = Carbon::createFromFormat('Y-m-d H:i:s', $workDate->copy()->subDay()->format('Y-m-d') . ' ' . $s->start_time, 'Asia/Ho_Chi_Minh');
                $ysEnd   = Carbon::createFromFormat('Y-m-d H:i:s', $workDate->copy()->subDay()->format('Y-m-d') . ' ' . $s->end_time,   'Asia/Ho_Chi_Minh');
                if ($ysEnd->lt($ysStart)) {
                    // Ca qua đêm → kết thúc sang ngày hiện tại
                    $ysEnd->addDay();
                }
                $yesterdayEnds[] = $ysEnd;
            }

            // Lấy các ca đã có trong ngày hiện tại (để kiểm tra khoảng nghỉ giữa 2 ca cùng ngày)
            $todayExisting = \App\Models\WorkAssignment::with('shift')
                ->where('employee_id', $employeeId)
                ->where('work_date', $workDate->toDateString())
                ->get();

            // Quy về khoảng thời gian (start, end) thực của các ca đã có trong ngày
            $todayIntervals = [];
            foreach ($todayExisting as $ex) {
                $s = $ex->shift;
                if (!$s) continue;

                $st = Carbon::createFromFormat('Y-m-d H:i:s', $workDate->format('Y-m-d') . ' ' . $s->start_time, 'Asia/Ho_Chi_Minh');
                $en = Carbon::createFromFormat('Y-m-d H:i:s', $workDate->format('Y-m-d') . ' ' . $s->end_time,   'Asia/Ho_Chi_Minh');
                if ($en->lt($st)) $en->addDay(); // ca qua đêm
                $todayIntervals[] = [$st, $en];
            }

            // Lấy thông tin các ca sắp gán
            $shiftMap = Shift::whereIn('id', $newShiftIds)->get()->keyBy('id');

            // Tạo mới các ca chưa có, có kiểm tra nghỉ tối thiểu
            foreach ($newShiftIds as $shiftId) {
                // Đã tồn tại thì bỏ qua (vì bên trên đã giữ lại)
                if (in_array($shiftId, $existingShiftIds)) {
                    continue;
                }

                $s = $shiftMap[$shiftId] ?? null;
                if (!$s) {
                    $skipped[] = [
                        'employee_id' => $employeeId,
                        'work_date'   => $date,
                        'shift_id'    => $shiftId,
                        'reason'      => 'Không tìm thấy ca làm (shift)',
                    ];
                    continue;
                }

                // Khoảng thời gian thực của ca mới
                $newStart = Carbon::createFromFormat('Y-m-d H:i:s', $workDate->format('Y-m-d') . ' ' . $s->start_time, 'Asia/Ho_Chi_Minh');
                $newEnd   = Carbon::createFromFormat('Y-m-d H:i:s', $workDate->format('Y-m-d') . ' ' . $s->end_time,   'Asia/Ho_Chi_Minh');
                if ($newEnd->lt($newStart)) $newEnd->addDay(); // ca qua đêm

                // 1) Kiểm tra nghỉ tối thiểu so với ca kết thúc hôm trước
                $violatesRest = false;
                foreach ($yesterdayEnds as $prevEnd) {
                    $restHours = $prevEnd->floatDiffInHours($newStart);
                    if ($restHours < $minRestHours) {
                        $violatesRest = true;
                        $skipped[] = [
                            'employee_id' => $employeeId,
                            'work_date'   => $date,
                            'shift_id'    => $shiftId,
                            'reason'      => "Không đủ thời gian nghỉ tối thiểu sau ca hôm trước (" . round($restHours, 1) . "h < {$minRestHours}h)",
                        ];
                        break;
                    }
                }
                if ($violatesRest) continue;

                // 2) Kiểm tra nghỉ tối thiểu giữa 2 ca trong cùng ngày (nếu đã có ca khác)
                foreach ($todayIntervals as [$st, $en]) {
                    // Trường hợp ca mới nằm sau ca đã có
                    if ($newStart->gte($en)) {
                        $restHours = $en->floatDiffInHours($newStart);
                        if ($restHours < $minRestHours) {
                            $violatesRest = true;
                            $skipped[] = [
                                'employee_id' => $employeeId,
                                'work_date'   => $date,
                                'shift_id'    => $shiftId,
                                'reason'      => "Khoảng nghỉ giữa 2 ca trong ngày không đủ (" . round($restHours, 1) . "h < {$minRestHours}h)",
                            ];
                            break;
                        }
                    }
                    // Trường hợp ca mới nằm trước ca đã có
                    if ($newEnd->lte($st)) {
                        $restHours = $newEnd->floatDiffInHours($st);
                        if ($restHours < $minRestHours) {
                            $violatesRest = true;
                            $skipped[] = [
                                'employee_id' => $employeeId,
                                'work_date'   => $date,
                                'shift_id'    => $shiftId,
                                'reason'      => "Khoảng nghỉ giữa 2 ca trong ngày không đủ (" . round($restHours, 1) . "h < {$minRestHours}h)",
                            ];
                            break;
                        }
                    }
                }
                if ($violatesRest) continue;

                // OK → tạo mới
                $created[] = \App\Models\WorkAssignment::create([
                    'employee_id' => $employeeId,
                    'work_date'   => $date,
                    'shift_id'    => $shiftId,
                ]);

                // Cập nhật todayIntervals để kiểm tra các ca tiếp theo trong cùng request
                $todayIntervals[] = [$newStart, $newEnd];
            }
        }

        return response()->json([
            'success'       => true,
            'message'       => 'Phân công nhiều ca đã được xử lý.',
            'created_count' => count($created),
            'deleted_count' => count($deleted),
            'skipped_count' => count($skipped),
            'data' => [
                'created' => $created,
                'deleted' => $deleted,
                'skipped' => $skipped,
            ],
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
