<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Models\WorkAssignment;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            'overtime_requests.*.overtime_type' => 'required|in:after_shift,custom',
            'overtime_requests.*.duration' => 'required_if:overtime_requests.*.overtime_type,after_shift|integer|min:1|max:6',
            'overtime_requests.*.start_datetime' => 'required_if:overtime_requests.*.overtime_type,custom|date_format:Y-m-d H:i',
            'overtime_requests.*.end_datetime' => 'required_if:overtime_requests.*.overtime_type,custom|date_format:Y-m-d H:i|after:overtime_requests.*.start_datetime',
            'overtime_requests.*.reason' => 'nullable|string',
        ]);

        $workDate = Carbon::parse($validated['work_date']);
        $requests = $validated['overtime_requests'];
        $now = Carbon::now();

        $successList = [];
        $errorList = [];

        DB::beginTransaction();
        try {
            foreach ($requests as $req) {
                $employeeId = $req['employee_id'];
                $employee = Employee::find($employeeId);

                if (!$employee) {
                    $errorList[] = ['employee_id' => $employeeId, 'reason' => 'Không tìm thấy nhân viên'];
                    continue;
                }

                // Không cho đăng ký cho ngày đã qua
                if ($workDate->lt($now->startOfDay())) {
                    $errorList[] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $employee->name,
                        'reason' => 'Không thể đăng ký tăng ca cho ngày đã qua'
                    ];
                    continue;
                }

                // Xóa OT cũ trong ngày để đảm bảo 1 bản ghi / ngày
                OvertimeRequest::where('employee_id', $employeeId)
                    ->where('work_date', $workDate)
                    ->delete();

                // Đếm số ca chính để tính giới hạn OT
                $mainShiftsCount = WorkAssignment::where('employee_id', $employeeId)
                    ->where('work_date', $workDate)
                    ->count();

                $maxAllowed = $mainShiftsCount >= 2 ? 0 : ($mainShiftsCount === 1 ? 4 : 6);

                if ($maxAllowed === 0) {
                    $errorList[] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $employee->name,
                        'reason' => 'Nhân viên đã làm đủ 2 ca, không được tăng ca'
                    ];
                    continue;
                }

                $startDatetime = null;
                $endDatetime = null;

                if ($req['overtime_type'] === 'after_shift') {
                    // Lấy ca chính cuối cùng trong ngày
                    $lastShift = WorkAssignment::join('shifts', 'work_assignments.shift_id', '=', 'shifts.id')
                        ->where('work_assignments.employee_id', $employeeId)
                        ->where('work_assignments.work_date', $workDate)
                        ->orderByDesc('shifts.end_time')
                        ->select('work_assignments.*', 'shifts.end_time')
                        ->first();

                    if (!$lastShift) {
                        $errorList[] = [
                            'employee_id' => $employeeId,
                            'employee_name' => $employee->name,
                            'reason' => 'Không có ca chính để tăng ca'
                        ];
                        continue;
                    }

                    // Tính start_datetime và end_datetime
                    $startDatetime = Carbon::parse($workDate->format('Y-m-d') . ' ' . $lastShift->end_time);
                    $endDatetime = $startDatetime->copy()->addHours($req['duration']);

                    // Validate thời gian đã qua
                    if ($endDatetime->lt($now)) {
                        $errorList[] = [
                            'employee_id' => $employeeId,
                            'employee_name' => $employee->name,
                            'reason' => 'Thời gian tăng ca đã qua'
                        ];
                        continue;
                    }
                } else {
                    // Custom giờ OT
                    $startDatetime = Carbon::parse($req['start_datetime']);
                    $endDatetime = Carbon::parse($req['end_datetime']);

                    if ($startDatetime->lt($now) && $endDatetime->lt($now)) {
                        $errorList[] = [
                            'employee_id' => $employeeId,
                            'employee_name' => $employee->name,
                            'reason' => 'Khoảng thời gian đã qua: ' . $startDatetime->format('H:i') . '-' . $endDatetime->format('H:i')
                        ];
                        continue;
                    }
                }

                // Kiểm tra giới hạn số giờ OT
                $otHours = $startDatetime->floatDiffInHours($endDatetime);
                if ($otHours > $maxAllowed) {
                    $errorList[] = [
                        'employee_id' => $employeeId,
                        'employee_name' => $employee->name,
                        'reason' => "Tăng ca $otHours tiếng, vượt giới hạn tối đa $maxAllowed tiếng"
                    ];
                    continue;
                }

                // Check trùng ca chính
                $hasMainShiftConflict = WorkAssignment::where('employee_id', $employeeId)
                    ->where('work_date', $workDate)
                    ->whereHas('shift', function ($q) use ($startDatetime, $endDatetime) {
                        $q->whereTime('start_time', '<', $endDatetime->toTimeString())
                            ->whereTime('end_time', '>', $startDatetime->toTimeString());
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

                // Tạo đơn OT
                OvertimeRequest::create([
                    'employee_id' => $employeeId,
                    'overtime_type' => $req['overtime_type'],
                    'work_date' => $workDate,
                    'start_datetime' => $startDatetime,
                    'end_datetime' => $endDatetime,
                    'reason' => $req['reason'] ?? null,
                ]);

                $successList[] = [
                    'employee_id' => $employeeId,
                    'employee_name' => $employee->name
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Xử lý đăng ký tăng ca hoàn tất.',
                'data' => [
                    'created' => $successList,
                    'errors' => $errorList
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, OvertimeRequest $overtimeRequest)
    {
        $validated = $request->validate([
            'work_date' => 'required|date',
            'overtime_type' => 'required|in:after_shift,custom',
            'duration' => 'required_if:overtime_type,after_shift|integer|min:1|max:6',
            'start_datetime' => 'required_if:overtime_type,custom|date_format:Y-m-d H:i',
            'end_datetime' => 'required_if:overtime_type,custom|date_format:Y-m-d H:i|after:start_datetime',
            'reason' => 'nullable|string',
        ]);

        $workDate = Carbon::parse($validated['work_date']);
        $now = Carbon::now();
        $employeeId = $overtimeRequest->employee_id;
        $employee = Employee::find($employeeId);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy nhân viên'
            ], 404);
        }

        // Không cho sửa cho ngày đã qua
        if ($workDate->lt($now->startOfDay())) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể chỉnh sửa tăng ca cho ngày đã qua'
            ], 400);
        }

        // Đếm số ca chính để tính giới hạn OT
        $mainShiftsCount = WorkAssignment::where('employee_id', $employeeId)
            ->where('work_date', $workDate)
            ->count();

        $maxAllowed = $mainShiftsCount >= 2 ? 0 : ($mainShiftsCount === 1 ? 4 : 6);

        if ($maxAllowed === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Nhân viên đã làm đủ 2 ca, không được tăng ca'
            ], 400);
        }

        $startDatetime = null;
        $endDatetime = null;

        if ($validated['overtime_type'] === 'after_shift') {
            // Lấy ca chính cuối cùng trong ngày
            $lastShift = WorkAssignment::join('shifts', 'work_assignments.shift_id', '=', 'shifts.id')
                ->where('work_assignments.employee_id', $employeeId)
                ->where('work_assignments.work_date', $workDate)
                ->orderByDesc('shifts.end_time')
                ->select('work_assignments.*', 'shifts.end_time')
                ->first();

            if (!$lastShift) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có ca chính để tăng ca'
                ], 400);
            }

            $startDatetime = Carbon::parse($workDate->format('Y-m-d') . ' ' . $lastShift->end_time);
            $endDatetime = $startDatetime->copy()->addHours($validated['duration']);

            if ($endDatetime->lt($now)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thời gian tăng ca đã qua'
                ], 400);
            }
        } else {
            // Custom giờ OT
            $startDatetime = Carbon::parse($validated['start_datetime']);
            $endDatetime = Carbon::parse($validated['end_datetime']);

            if ($startDatetime->lt($now) && $endDatetime->lt($now)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khoảng thời gian đã qua: ' . $startDatetime->format('H:i') . '-' . $endDatetime->format('H:i')
                ], 400);
            }
        }

        // Kiểm tra giới hạn số giờ OT
        $otHours = $startDatetime->floatDiffInHours($endDatetime);
        if ($otHours > $maxAllowed) {
            return response()->json([
                'success' => false,
                'message' => "Tăng ca $otHours tiếng, vượt giới hạn tối đa $maxAllowed tiếng"
            ], 400);
        }

        // Check trùng ca chính
        $hasMainShiftConflict = WorkAssignment::where('employee_id', $employeeId)
            ->where('work_date', $workDate)
            ->whereHas('shift', function ($q) use ($startDatetime, $endDatetime) {
                $q->whereTime('start_time', '<', $endDatetime->toTimeString())
                    ->whereTime('end_time', '>', $startDatetime->toTimeString());
            })
            ->exists();

        if ($hasMainShiftConflict) {
            return response()->json([
                'success' => false,
                'message' => 'Thời gian tăng ca trùng với ca chính'
            ], 400);
        }

        // Cập nhật OT
        $overtimeRequest->update([
            'overtime_type' => $validated['overtime_type'],
            'work_date' => $workDate,
            'start_datetime' => $startDatetime,
            'end_datetime' => $endDatetime,
            'reason' => $validated['reason'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật tăng ca thành công',
            'data' => $overtimeRequest
        ]);
    }

    public function destroy(OvertimeRequest $overtimeRequest)
    {
        $overtimeRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa bản ghi tăng ca thành công.'
        ]);
    }
}
