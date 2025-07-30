<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\WorkAssignment;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class OvertimeRequestController extends Controller
{

    // use AuthorizesRequests;

    // public function __construct()
    // {
    //     $this->authorizeResource(OvertimeRequest::class, 'overtime_requests');
    // }

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
        $request->validate([
            'overtimes' => 'required|array|min:1',
            'overtimes.*.employee_id' => 'required|exists:employees,id',
            'overtimes.*.work_date' => 'required|date',
            'overtimes.*.start_time' => 'nullable|date_format:H:i',
            'overtimes.*.end_time' => 'nullable|date_format:H:i|after:overtimes.*.start_time',
            'overtimes.*.reason' => 'nullable|string|max:500',
        ]);

        $created = [];
        $updated = [];
        $deleted = [];
        $skipped = [];

        $today = now()->format('Y-m-d');

        foreach ($request->overtimes as $item) {
            $employeeId = $item['employee_id'];
            $date = $item['work_date'];
            $startTime = $item['start_time'] ?? null;
            $endTime = $item['end_time'] ?? null;

            if ($date < $today) {
                $skipped[] = [
                    'employee_id' => $employeeId,
                    'work_date' => $date,
                    'reason' => 'Không thể đăng ký tăng ca cho ngày đã qua'
                ];
                continue;
            }

            // Lấy danh sách ca làm của nhân viên hôm đó
            $shiftsToday = WorkAssignment::where('employee_id', $employeeId)
                ->where('work_date', $date)
                ->with('shift')
                ->get();

            if ($shiftsToday->count() >= 2) {
                $skipped[] = [
                    'employee_id' => $employeeId,
                    'work_date' => $date,
                    'reason' => 'Nhân viên đã làm 2 ca trong ngày, không thể đăng ký tăng ca'
                ];
                continue;
            }

            $hasShift = $shiftsToday->isNotEmpty();
            $maxAllowedHours = $hasShift ? 4 : 6;

            if ($startTime && $endTime) {
                $start = Carbon::createFromFormat('H:i', $startTime);
                $end = Carbon::createFromFormat('H:i', $endTime);
                $duration = $end->diffInHours($start);

                if ($duration > $maxAllowedHours) {
                    $skipped[] = [
                        'employee_id' => $employeeId,
                        'work_date' => $date,
                        'reason' => 'Thời lượng tăng ca vượt quá giới hạn (' . $maxAllowedHours . 'h)'
                    ];
                    continue;
                }

                // Kiểm tra trùng giờ ca làm
                $conflict = false;
                foreach ($shiftsToday as $assignment) {
                    if ($assignment->shift) {
                        $shiftStart = Carbon::createFromFormat('H:i:s', $assignment->shift->start_time);
                        $shiftEnd = Carbon::createFromFormat('H:i:s', $assignment->shift->end_time);
                        if (
                            $start < $shiftEnd &&
                            $end > $shiftStart
                        ) {
                            $conflict = true;
                            break;
                        }
                    }
                }

                if ($conflict) {
                    $skipped[] = [
                        'employee_id' => $employeeId,
                        'work_date' => $date,
                        'reason' => 'Thời gian tăng ca trùng với ca làm chính'
                    ];
                    continue;
                }
            }

            $existing = OvertimeRequest::where('employee_id', $employeeId)
                ->where('work_date', $date)
                ->first();

            if ($startTime && $endTime) {
                if ($existing) {
                    if (
                        $existing->start_time !== $startTime ||
                        $existing->end_time !== $endTime ||
                        $existing->reason !== ($item['reason'] ?? null)
                    ) {
                        $existing->update([
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'reason' => $item['reason'] ?? null,
                        ]);
                        $updated[] = $existing;
                    }
                } else {
                    $created[] = OvertimeRequest::create([
                        'employee_id' => $employeeId,
                        'work_date' => $date,
                        'start_time' => $startTime,
                        'end_time' => $endTime,
                        'reason' => $item['reason'] ?? null,
                    ]);
                }
            } else {
                if ($existing) {
                    $existing->delete();
                    $deleted[] = [
                        'employee_id' => $employeeId,
                        'work_date' => $date,
                        'reason' => 'Xoá phiếu tăng ca do không có thời gian'
                    ];
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Phiếu tăng ca đã được xử lý.',
            'created_count' => count($created),
            'updated_count' => count($updated),
            'deleted_count' => count($deleted),
            'skipped_count' => count($skipped),
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'deleted' => $deleted,
                'skipped' => $skipped,
            ]
        ]);
    }
}
