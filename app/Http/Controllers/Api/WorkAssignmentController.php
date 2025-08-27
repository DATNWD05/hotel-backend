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
        'assignments.*.work_date'   => 'required|date',
        'assignments.*.shift_ids'   => 'nullable|array',
        'assignments.*.shift_ids.*' => 'exists:shifts,id',
    ]);

    $created = [];
    $deleted = [];
    $skipped = [];

    $tz    = 'Asia/Ho_Chi_Minh';
    $today = now($tz)->format('Y-m-d');

    foreach ($request->assignments as $assignment) {
        $employeeId  = $assignment['employee_id'];
        $date        = $assignment['work_date'];
        $newShiftIds = $assignment['shift_ids'] ?? [];

        // 0) Kiểm tra trạng thái nhân viên
        $employee = \App\Models\Employee::find($employeeId);
        if (!$employee || strtolower($employee->status) !== 'active') {
            $skipped[] = [
                'employee_id' => $employeeId,
                'work_date'   => $date,
                'reason'      => 'Nhân viên đã nghỉ việc hoặc không hoạt động',
            ];
            continue;
        }

        // 1) Không cho phân công cho ngày đã qua
        if ($date < $today) {
            $skipped[] = [
                'employee_id' => $employeeId,
                'work_date'   => $date,
                'reason'      => 'Không thể phân công cho ngày đã qua',
            ];
            continue;
        }

        // 2) Giới hạn tối đa 2 ca/ngày (theo input)
        if (count($newShiftIds) > 2) {
            $skipped[] = [
                'employee_id' => $employeeId,
                'work_date'   => $date,
                'reason'      => 'Vượt quá giới hạn 2 ca/ngày',
            ];
            continue;
        }

        // 3) Lấy các ca đã phân trong ngày (để xóa ca không còn)
        $existingAssignments = \App\Models\WorkAssignment::where('employee_id', $employeeId)
            ->where('work_date', $date)
            ->get();
        $existingShiftIds = $existingAssignments->pluck('shift_id')->toArray();

        // 3.a) Xóa những ca không còn trong danh sách mới
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

        // 4) Xác định hôm qua có ca đêm hay không
        $workDate   = \Carbon\Carbon::parse($date, $tz);
        $yesterday  = $workDate->copy()->subDay()->toDateString();

        $yesterdayAssignments = \App\Models\WorkAssignment::with('shift')
            ->where('employee_id', $employeeId)
            ->where('work_date', $yesterday)
            ->get();

        $hadNightYesterday = false;
        foreach ($yesterdayAssignments as $ya) {
            $s = $ya->shift;
            if (!$s) continue;
            // Ca đêm: end_time <= start_time (qua ngày)
            $startT = \Carbon\Carbon::createFromFormat('H:i:s', $s->start_time, $tz);
            $endT   = \Carbon\Carbon::createFromFormat('H:i:s', $s->end_time,   $tz);
            if ($endT->lessThanOrEqualTo($startT)) {
                $hadNightYesterday = true;
                break;
            }
        }

        // 5) Khoảng thời gian các ca đã có trong ngày (để chống chồng giờ)
        $todayExisting = \App\Models\WorkAssignment::with('shift')
            ->where('employee_id', $employeeId)
            ->where('work_date', $workDate->toDateString())
            ->get();

        $todayIntervals = [];
        foreach ($todayExisting as $ex) {
            $s = $ex->shift;
            if (!$s) continue;
            $st = \Carbon\Carbon::parse($workDate->format('Y-m-d') . ' ' . $s->start_time, $tz);
            $en = \Carbon\Carbon::parse($workDate->format('Y-m-d') . ' ' . $s->end_time,   $tz);
            if ($en->lessThanOrEqualTo($st)) $en->addDay(); // ca đêm
            $todayIntervals[] = [$st, $en];
        }

        // 6) Map shifts
        $shiftMap = \App\Models\Shift::whereIn('id', $newShiftIds)->get()->keyBy('id');

        // 7) Tạo mới các ca chưa có, theo quy tắc mới
        foreach ($newShiftIds as $shiftId) {
            // đã tồn tại → bỏ qua
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

            // Xác định ca này có phải ca đêm không
            $startT = \Carbon\Carbon::createFromFormat('H:i:s', $s->start_time, $tz);
            $endT   = \Carbon\Carbon::createFromFormat('H:i:s', $s->end_time,   $tz);
            $isNight = $endT->lessThanOrEqualTo($startT);

            // QUY TẮC MỚI:
            // - Nếu hôm qua có ca đêm → hôm nay CHỈ ĐƯỢC ca đêm
            if ($hadNightYesterday && !$isNight) {
                $skipped[] = [
                    'employee_id' => $employeeId,
                    'work_date'   => $date,
                    'shift_id'    => $shiftId,
                    'reason'      => 'Hôm qua đã làm ca đêm → hôm nay chỉ được phân ca đêm',
                ];
                continue;
            }

            // Tính interval thực của ca mới để chống chồng giờ trong ngày
            $newStart = \Carbon\Carbon::parse($workDate->format('Y-m-d') . ' ' . $s->start_time, $tz);
            $newEnd   = \Carbon\Carbon::parse($workDate->format('Y-m-d') . ' ' . $s->end_time,   $tz);
            if ($newEnd->lessThanOrEqualTo($newStart)) $newEnd->addDay(); // ca đêm

            // Không cho phép CHỒNG/GIAO nhau với ca đã có trong ngày
            $overlap = false;
            foreach ($todayIntervals as [$st, $en]) {
                // overlap nếu max(start) < min(end)
                if ($newStart->lt($en) && $st->lt($newEnd)) {
                    $overlap = true;
                    break;
                }
            }
            if ($overlap) {
                $skipped[] = [
                    'employee_id' => $employeeId,
                    'work_date'   => $date,
                    'shift_id'    => $shiftId,
                    'reason'      => 'Thời gian ca mới bị chồng với ca đã có trong ngày',
                ];
                continue;
            }

            // OK → tạo mới
            $created[] = \App\Models\WorkAssignment::create([
                'employee_id' => $employeeId,
                'work_date'   => $date,
                'shift_id'    => $shiftId,
            ]);

            // cập nhật intervals để kiểm soát các ca sau trong cùng request
            $todayIntervals[] = [$newStart, $newEnd];

            // Vẫn đảm bảo tối đa 2 ca/ngày theo input => điều kiện ở đầu đã chặn >2,
            // còn nếu bạn muốn “cứng” thêm: khi đã đủ 2 interval thì dừng
            if (count($todayIntervals) >= 2) {
                // dừng sớm để tránh vô tình thêm tiếp (nếu client gửi trùng lặp)
                break;
            }
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

    // public function import(Request $request)
    // {
    //     if (!$request->hasFile('file')) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Vui lòng chọn file Excel.'
    //         ], 400);
    //     }

    //     try {
    //         Excel::import(new WorkAssignmentImport, $request->file('file'));
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Import phân công thành công!'
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Lỗi khi import: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }
}
