<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\WorkAssignment;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class OvertimeRequestController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(OvertimeRequest::class, 'overtime_requests');
    }

    public function index(Request $request)
    {
        $query = OvertimeRequest::with('employee');

        if ($request->has('date')) {
            $query->where('work_date', $request->date);
        }

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('work_date')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'work_date' => 'required|date',
            'overtime_requests' => 'required|array',
            'overtime_requests.*.employee_id' => 'required|exists:employees,id',
            'overtime_requests.*.start_datetime' => 'required|date_format:Y-m-d H:i',
            'overtime_requests.*.end_datetime' => 'required|date_format:Y-m-d H:i|after:overtime_requests.*.start_datetime',
            'overtime_requests.*.reason' => 'nullable|string',
        ]);

        $workDate = Carbon::parse($validated['work_date']);
        $requests = $validated['overtime_requests'];
        $now = Carbon::now();

        $successList = [];
        $errorList = [];

        foreach ($requests as $req) {
            $employeeId = $req['employee_id'];
            $startDateTime = Carbon::parse($req['start_datetime']);
            $endDateTime = Carbon::parse($req['end_datetime']);
            $reason = $req['reason'] ?? null;

            $employee = Employee::find($employeeId);
            if (!$employee) {
                $errorList[] = ['employee_id' => $employeeId, 'reason' => 'Không tìm thấy nhân viên'];
                continue;
            }

            if ($startDateTime->lt($now) && $endDateTime->lt($now)) {
                $errorList[] = [
                    'employee_id' => $employeeId,
                    'reason' => 'Khoảng thời gian tăng ca đã hoàn toàn qua: ' . $startDateTime->toDateTimeString() . ' - ' . $endDateTime->toDateTimeString()
                ];
                continue;
            }

            $mainShiftsCount = WorkAssignment::where('employee_id', $employeeId)
                ->where('work_date', $workDate)
                ->count();

            $otHours = $startDateTime->floatDiffInHours($endDateTime);
            $maxAllowed = $mainShiftsCount >= 2 ? 0 : ($mainShiftsCount === 1 ? 4 : 6);

            if ($otHours > $maxAllowed) {
                $errorList[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee->name,
                    'reason' => "Tăng ca {$otHours} tiếng, vượt giới hạn tối đa {$maxAllowed} tiếng (đã được phân công {$mainShiftsCount} ca)"
                ];
                continue;
            }

            if ($maxAllowed === 0) {
                $errorList[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee->name,
                    'reason' => 'Đã được phân công đủ 2 ca chính, không được tăng ca'
                ];
                continue;
            }

            $hasMainShiftConflict = WorkAssignment::where('employee_id', $employeeId)
                ->where('work_date', $workDate)
                ->whereHas('shift', function ($q) use ($startDateTime, $endDateTime) {
                    $q->whereTime('start_time', '<', $endDateTime->toTimeString())
                        ->whereTime('end_time', '>', $startDateTime->toTimeString());
                })
                ->exists();

            if ($hasMainShiftConflict) {
                $errorList[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee->name,
                    'reason' => 'Thời gian tăng ca trùng với ca chính đã được phân công'
                ];
                continue;
            }

            OvertimeRequest::where('employee_id', $employeeId)
                ->where('work_date', $workDate)
                ->delete();

            $hasOvertimeConflict = OvertimeRequest::where('employee_id', $employeeId)
                ->where('work_date', $workDate)
                ->where(function ($q) use ($startDateTime, $endDateTime) {
                    $q->where(function ($query) use ($startDateTime, $endDateTime) {
                        $query->whereTime('start_datetime', '<', $endDateTime->toTimeString())
                            ->whereTime('end_datetime', '>', $startDateTime->toTimeString());
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

            OvertimeRequest::create([
                'employee_id' => $employeeId,
                'work_date' => $workDate,
                'start_datetime' => $startDateTime,
                'end_datetime' => $endDateTime,
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
            'data' => ['created' => $successList, 'errors' => $errorList]
        ]);
    }
}
