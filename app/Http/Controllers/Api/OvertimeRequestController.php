<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\WorkAssignment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Attendance;
use App\Models\Employee;



class OvertimeRequestController extends Controller
{

    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(OvertimeRequest::class, 'overtime_requests');
    }

    /**
     * Lấy danh sách tất cả phiếu tăng ca (tuỳ chọn lọc theo ngày hoặc nhân viên)
     */
    public function index(Request $request)
    {
        $query = OvertimeRequest::with('employee');

        // Nếu có lọc theo ngày
        if ($request->has('date')) {
            $query->where('work_date', $request->date);
        }

        // Nếu có lọc theo nhân viên
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('work_date')->get(),
        ]);
    }

    /**
     * Tạo phiếu tăng ca mới
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'work_date' => 'required|date',
            'overtime_requests' => 'required|array',
            'overtime_requests.*.employee_id' => 'required|exists:employees,id',
            'overtime_requests.*.start_time' => 'required|date_format:H:i',
            'overtime_requests.*.end_time' => 'required|date_format:H:i|after:overtime_requests.*.start_time',
            'overtime_requests.*.reason' => 'nullable|string',
        ]);

        $workDate = Carbon::parse($validated['work_date']);
        $requests = $validated['overtime_requests'];

        // Kiểm tra ngày đã qua
        if ($workDate->lt(Carbon::today())) {
            return response()->json([
                'success' => false,
                'message' => 'Không được phép đăng ký tăng ca cho ngày đã qua.',
                'data' => [
                    'created' => [],
                    'errors' => [
                        [
                            'reason' => 'Ngày làm việc đã qua: ' . $workDate->toDateString()
                        ]
                    ]
                ]
            ], 422);
        }

        $successList = [];
        $errorList = [];

        foreach ($requests as $req) {
            $employeeId = $req['employee_id'];
            $startTime = $req['start_time'];
            $endTime = $req['end_time'];
            $reason = $req['reason'] ?? null;

            $employee = Employee::find($employeeId);
            if (!$employee) {
                $errorList[] = [
                    'employee_id' => $employeeId,
                    'reason' => 'Không tìm thấy nhân viên'
                ];
                continue;
            }

            // Đếm số ca chính trong ngày
            $mainShiftsCount = Attendance::where('employee_id', $employeeId)
                ->whereDate('check_in', $workDate)
                ->count();

            // Tạo đối tượng thời gian đầy đủ cho start_time và end_time
            $startDateTime = Carbon::parse($workDate)->setTimeFromTimeString($startTime);
            $endDateTime = Carbon::parse($workDate)->setTimeFromTimeString($endTime);

            // Nếu end_time nhỏ hơn start_time, giả định end_time thuộc ngày tiếp theo
            if ($endDateTime->lt($startDateTime)) {
                $endDateTime->addDay();
            }

            // Tính số giờ tăng ca
            $otHours = $startDateTime->floatDiffInHours($endDateTime);

            // Giới hạn giờ tăng ca
            $maxAllowed = $mainShiftsCount >= 2 ? 0 : ($mainShiftsCount === 1 ? 4 : 6);

            if ($otHours > $maxAllowed) {
                $errorList[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee->name,
                    'reason' => "Tăng ca {$otHours} tiếng, vượt giới hạn tối đa {$maxAllowed} tiếng (đã làm {$mainShiftsCount} ca)"
                ];
                continue;
            }

            if ($maxAllowed === 0) {
                $errorList[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee->name,
                    'reason' => 'Đã làm đủ 2 ca chính, không được tăng ca'
                ];
                continue;
            }

            // Kiểm tra trùng với ca chính
            $hasMainShiftConflict = Attendance::where('employee_id', $employeeId)
                ->whereDate('check_in', $workDate)
                ->where(function ($q) use ($startDateTime, $endDateTime) {
                    $q->whereTime('check_in', '<', $endDateTime->toTimeString())
                        ->whereTime('check_out', '>', $startDateTime->toTimeString());
                })
                ->exists();

            if ($hasMainShiftConflict) {
                $errorList[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee->name,
                    'reason' => 'Thời gian tăng ca trùng với ca chính'
                ];
                continue;
            }

            // Xoá đơn tăng ca cũ cùng ngày
            OvertimeRequest::where('employee_id', $employeeId)
                ->where('work_date', $workDate)
                ->delete();

            // Kiểm tra trùng thời gian với các đơn tăng ca khác
            $hasOvertimeConflict = OvertimeRequest::where('employee_id', $employeeId)
                ->where('work_date', $workDate)
                ->where(function ($q) use ($startDateTime, $endDateTime) {
                    $q->where(function ($query) use ($startDateTime, $endDateTime) {
                        $query->whereTime('start_time', '<', $endDateTime->toTimeString())
                            ->whereTime('end_time', '>', $startDateTime->toTimeString());
                    });
                })->exists();

            if ($hasOvertimeConflict) {
                $errorList[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee->name,
                    'reason' => 'Thời gian trùng với đơn tăng ca khác'
                ];
                continue;
            }

            // Lưu đơn tăng ca mới
            OvertimeRequest::create([
                'employee_id' => $employeeId,
                'work_date' => $workDate,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'reason' => $reason,
            ]);

            $successList[] = [
                'employee_id' => $employeeId,
                'employee_name' => $employee->name,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Xử lý đăng ký tăng ca hoàn tất.',
            'data' => [
                'created' => $successList,
                'errors' => $errorList
            ]
        ]);
    }
}
