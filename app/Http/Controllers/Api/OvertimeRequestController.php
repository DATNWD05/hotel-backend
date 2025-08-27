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

    // use AuthorizesRequests;

    // public function __construct()
    // {
    //     $this->authorizeResource(OvertimeRequest::class, 'overtime_requests');
    // }

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
            'overtime_requests' => 'required|array|min:1',

            'overtime_requests.*.employee_id'   => 'required|exists:employees,id',
            'overtime_requests.*.overtime_type' => 'required|in:after_shift,custom',

            // after_shift: nhập số giờ OT
            'overtime_requests.*.duration'      => 'required_if:overtime_requests.*.overtime_type,after_shift|integer|min:1|max:6',

            // custom: nhập cặp thời gian đầy đủ
            'overtime_requests.*.start_datetime' => 'required_if:overtime_requests.*.overtime_type,custom|date_format:Y-m-d H:i',
            'overtime_requests.*.end_datetime'   => 'required_if:overtime_requests.*.overtime_type,custom|date_format:Y-m-d H:i|after:overtime_requests.*.start_datetime',

            'overtime_requests.*.reason'         => 'nullable|string',
        ]);

        $workDate = Carbon::parse($validated['work_date']);    // ngày OT (calendar day)
        $dayStart = $workDate->copy()->startOfDay();
        $dayEnd   = $workDate->copy()->endOfDay();
        $prevDate = $workDate->copy()->subDay()->toDateString();
        $now      = Carbon::now();

        $successList = [];
        $errorList   = [];

        // Helper: đổi 1 phân công -> khoảng datetime thật (xử lý ca qua ngày)
        $buildInterval = function ($wa) {
            $s = Carbon::parse($wa->work_date . ' ' . $wa->shift->start_time);
            $e = Carbon::parse($wa->work_date . ' ' . $wa->shift->end_time);
            if ($e->lte($s)) $e->addDay(); // ca đêm vắt qua 0h
            return [$s, $e];
        };

        DB::beginTransaction();
        try {
            foreach ($validated['overtime_requests'] as $req) {
                $employeeId = $req['employee_id'];

                // Không cho đăng ký cho ngày đã qua (tính theo calendar day)
                if ($dayEnd->lt($now)) {
                    $errorList[] = [
                        'employee_id'   => $employeeId,
                        'employee_name' => optional(Employee::find($employeeId))->name,
                        'reason'        => 'Không thể đăng ký tăng ca cho ngày đã qua'
                    ];
                    continue;
                }

                // Lấy mọi phân công ảnh hưởng tới ngày OT: chính ngày + ngày liền trước
                $assignments = WorkAssignment::with('shift')
                    ->where('employee_id', $employeeId)
                    ->whereIn('work_date', [$workDate->toDateString(), $prevDate])
                    ->get();

                // Nếu cần bắt buộc after_shift phải có ca chính
                if (($req['overtime_type'] ?? null) === 'after_shift' && $assignments->isEmpty()) {
                    $errorList[] = [
                        'employee_id'   => $employeeId,
                        'employee_name' => optional(Employee::find($employeeId))->name,
                        'reason'        => 'Không có ca chính liên quan để tăng ca sau ca'
                    ];
                    continue;
                }

                // Build các khoảng ca chính thực tế
                $intervals = [];
                foreach ($assignments as $wa) {
                    [$s, $e] = $buildInterval($wa);
                    $intervals[] = [$s, $e];
                }

                // Đếm số ca chính giao với calendar day của $workDate để tính giới hạn
                $mainShiftsCount = 0;
                foreach ($intervals as [$s, $e]) {
                    if ($s < $dayEnd && $e > $dayStart) $mainShiftsCount++;
                }

                $maxAllowed = $mainShiftsCount >= 2 ? 0 : ($mainShiftsCount === 1 ? 4 : 6);
                if ($maxAllowed === 0) {
                    $errorList[] = [
                        'employee_id'   => $employeeId,
                        'employee_name' => optional(Employee::find($employeeId))->name,
                        'reason'        => 'Nhân viên đã làm đủ 2 ca, không được tăng ca'
                    ];
                    continue;
                }

                // Tính khoảng OT cần lưu
                $startDatetime = null;
                $endDatetime   = null;

                if (($req['overtime_type'] ?? null) === 'after_shift') {
                    // Tìm ca kết thúc muộn nhất (liên quan tới ngày OT)
                    if (empty($intervals)) {
                        $errorList[] = [
                            'employee_id'   => $employeeId,
                            'employee_name' => optional(Employee::find($employeeId))->name,
                            'reason'        => 'Không có ca chính liên quan để tăng ca sau ca'
                        ];
                        continue;
                    }
                    usort($intervals, fn($a, $b) => $b[1] <=> $a[1]);
                    $lastEnd = $intervals[0][1]->copy();

                    $duration = (int) ($req['duration'] ?? 0);
                    $startDatetime = $lastEnd;                    // liền sau ca chính
                    $endDatetime   = $startDatetime->copy()->addHours($duration);

                    // Không cho tạo khoảng đã hoàn toàn qua
                    if ($endDatetime->lt($now)) {
                        $errorList[] = [
                            'employee_id'   => $employeeId,
                            'employee_name' => optional(Employee::find($employeeId))->name,
                            'reason'        => 'Thời gian tăng ca đã qua'
                        ];
                        continue;
                    }
                } else {
                    // custom
                    $startDatetime = Carbon::parse($req['start_datetime']);
                    $endDatetime   = Carbon::parse($req['end_datetime']);

                    // Không cho tạo khoảng hoàn toàn nằm trong quá khứ
                    if ($startDatetime->lt($now) && $endDatetime->lt($now)) {
                        $errorList[] = [
                            'employee_id'   => $employeeId,
                            'employee_name' => optional(Employee::find($employeeId))->name,
                            'reason'        => 'Khoảng thời gian đã qua: ' . $startDatetime->format('H:i') . '-' . $endDatetime->format('H:i')
                        ];
                        continue;
                    }
                }

                // Giới hạn số giờ OT
                $otHours = $startDatetime->floatDiffInHours($endDatetime);
                if ($otHours > $maxAllowed) {
                    $errorList[] = [
                        'employee_id'   => $employeeId,
                        'employee_name' => optional(Employee::find($employeeId))->name,
                        'reason'        => "Tăng ca {$otHours} tiếng, vượt giới hạn tối đa {$maxAllowed} tiếng (đã tính cả ca đêm vắt ngày)"
                    ];
                    continue;
                }

                // Chặn trùng CA CHÍNH (kể cả ca đêm hôm trước vắt sang)
                $overlapMain = false;
                foreach ($intervals as [$s, $e]) {
                    if ($s < $endDatetime && $e > $startDatetime) {
                        $overlapMain = true;
                        break;
                    }
                }
                if ($overlapMain) {
                    $errorList[] = [
                        'employee_id'   => $employeeId,
                        'employee_name' => optional(Employee::find($employeeId))->name,
                        'reason'        => 'Thời gian tăng ca trùng với ca chính'
                    ];
                    continue;
                }

                // (Tuỳ chính sách) chỉ cho 1 bản ghi OT / ngày: xoá cái cũ trước khi tạo
                OvertimeRequest::where('employee_id', $employeeId)
                    ->whereDate('work_date', $workDate)
                    ->delete();

                // Chặn trùng với OT khác còn lại trong DB (nếu bạn cho phép nhiều bản ghi / ngày thì giữ block này)
                $hasOvertimeConflict = OvertimeRequest::where('employee_id', $employeeId)
                    ->whereDate('work_date', $workDate)
                    ->where(function ($q) use ($startDatetime, $endDatetime) {
                        $q->where('start_datetime', '<', $endDatetime)
                            ->where('end_datetime',   '>', $startDatetime);
                    })
                    ->exists();
                if ($hasOvertimeConflict) {
                    $errorList[] = [
                        'employee_id'   => $employeeId,
                        'employee_name' => optional(Employee::find($employeeId))->name,
                        'reason'        => 'Thời gian trùng với đơn tăng ca khác'
                    ];
                    continue;
                }

                // Tạo OT
                OvertimeRequest::create([
                    'employee_id'    => $employeeId,
                    'overtime_type'  => $req['overtime_type'],
                    'work_date'      => $workDate,         // vẫn neo theo calendar day
                    'start_datetime' => $startDatetime,
                    'end_datetime'   => $endDatetime,
                    'reason'         => $req['reason'] ?? null,
                ]);

                $successList[] = [
                    'employee_id'   => $employeeId,
                    'employee_name' => optional(Employee::find($employeeId))->name,
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Xử lý đăng ký tăng ca hoàn tất.',
                'data'    => ['created' => $successList, 'errors' => $errorList]
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(OvertimeRequest $overtimeRequest)
    {
        $overtimeRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa đơn tăng ca.',
        ]);
    }
}
