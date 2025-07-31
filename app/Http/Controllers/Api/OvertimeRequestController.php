<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\Attendance;
use App\Models\Employee;

class OvertimeRequestController extends Controller
{
    /**
     * Lấy danh sách tất cả phiếu tăng ca (tuỳ chọn lọc theo ngày hoặc nhân viên)
     */
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
            'overtime_requests.*.end_time' => 'required|date_format:H:i', // Loại bỏ 'after'
            'overtime_requests.*.reason' => 'nullable|string',
        ]);

        $workDate = Carbon::parse($validated['work_date']);
        $requests = $validated['overtime_requests'];
        $now = Carbon::now();

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

            // Tạo đối tượng thời gian đầy đủ
            $startDateTime = Carbon::parse($workDate)->setTimeFromTimeString($startTime);
            $endDateTime = Carbon::parse($workDate)->setTimeFromTimeString($endTime);

            // Xử lý ca đêm: nếu end_time < start_time, thêm 1 ngày cho endDateTime
            if ($endDateTime->lt($startDateTime)) {
                $endDateTime->addDay();
            }

            // Kiểm tra tính hợp lệ của khoảng thời gian
            if ($startDateTime->lt($now) && $endDateTime->lt($now)) {
                $errorList[] = [
                    'employee_id' => $employeeId,
                    'reason' => 'Khoảng thời gian tăng ca đã hoàn toàn qua: ' . $startDateTime->toDateTimeString() . ' - ' . $endDateTime->toDateTimeString()
                ];
                continue;
            }

            // Đếm số ca chính trong ngày work_date
            $mainShiftsCount = Attendance::where('employee_id', $employeeId)
                ->whereDate('check_in', $workDate)
                ->count();

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
