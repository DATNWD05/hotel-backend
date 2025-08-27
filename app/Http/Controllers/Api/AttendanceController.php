<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;
use App\Models\EmployeeFace;
use App\Models\Shift;
use App\Models\WorkAssignment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\OvertimeRequest;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = Attendance::with(['employee', 'shift']);

        if ($request->filled('from_date')) {
            $query->whereDate('work_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('work_date', '<=', $request->input('to_date'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        $perPage = $request->input('per_page', 10);
        $attendances = $query->orderByDesc('work_date')->paginate($perPage);

        $data = $attendances->map(function ($attendance) {
            return [
                'id' => $attendance->id,
                'employee_name' => $attendance->employee->name ?? 'N/A',
                'shift_name' => $attendance->shift->name ?? 'N/A',
                'work_date' => $attendance->work_date,
                'check_in' => $attendance->check_in,
                'check_out' => $attendance->check_out,
                'worked_hours' => $attendance->worked_hours,
                'late_minutes' => $attendance->late_minutes,
                'early_leave_minutes' => $attendance->early_leave_minutes,
                'overtime_hours' => $attendance->overtime_hours,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'current_page' => $attendances->currentPage(),
                'per_page' => $attendances->perPage(),
                'total' => $attendances->total(),
                'last_page' => $attendances->lastPage(),
            ],
        ]);
    }

   public function faceAttendance(Request $request)
    {
        $request->validate(['image' => 'required|string']);

        // Decode ảnh base64
        $base64Image = preg_replace('#^data:image/\w+;base64,#i', '', $request->input('image'));
        $base64Image = str_replace(' ', '+', $base64Image);
        Storage::put("debug_camera.jpg", base64_decode($base64Image));

        // Detect khuôn mặt
        $detectResponse = Http::withoutVerifying()->asForm()->post('https://api-us.faceplusplus.com/facepp/v3/detect', [
            'api_key' => env('FACEPP_KEY'),
            'api_secret' => env('FACEPP_SECRET'),
            'image_base64' => $base64Image,
        ]);
        if (!$detectResponse->successful() || count($detectResponse['faces'] ?? []) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Không phát hiện được khuôn mặt. Vui lòng chụp chính diện, đủ sáng.',
            ], 400);
        }

        // Tìm nhân viên trùng khuôn mặt
        $faces = EmployeeFace::with('employee')->get();
        foreach ($faces as $face) {
            if (!Storage::disk('public')->exists($face->image_path)) continue;

            $faceBase64 = base64_encode(Storage::disk('public')->get($face->image_path));
            $compareResponse = Http::withoutVerifying()->asForm()->post('https://api-us.faceplusplus.com/facepp/v3/compare', [
                'api_key' => env('FACEPP_KEY'),
                'api_secret' => env('FACEPP_SECRET'),
                'image_base64_1' => $faceBase64,
                'image_base64_2' => $base64Image,
            ]);

            if (!$compareResponse->successful() || ($compareResponse['confidence'] ?? 0) < 85) {
                continue;
            }

            $employee = $face->employee;
            $now = now()->setTimezone('Asia/Ho_Chi_Minh');
            $today = $now->toDateString();
            $yesterday = $now->copy()->subDay()->toDateString();

            // Lấy phân công hôm nay + hôm qua để hỗ trợ ca đêm
            $assignments = WorkAssignment::with('shift')
                ->where('employee_id', $employee->id)
                ->whereIn('work_date', [$yesterday, $today])
                ->get();

            if ($assignments->count() > 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Số ca chính vượt quá giới hạn 2 ca/ngày.',
                ], 422);
            }

            // Lấy toàn bộ đơn OT liên quan (hôm qua + hôm nay)
            $otList = OvertimeRequest::where('employee_id', $employee->id)
                ->whereIn('work_date', [$yesterday, $today])
                ->get();

            // ====================================================
            // 1) ƯU TIÊN CHECK-OUT CHO OT CUSTOM ĐANG MỞ
            // ====================================================
            $openCustom = Attendance::where('employee_id', $employee->id)
                ->whereNull('shift_id')
                ->where('is_overtime', 1)
                ->whereNull('check_out')
                ->orderByDesc('work_date')
                ->first();

            if ($openCustom) {
                $ot = $otList->firstWhere('id', $openCustom->overtime_request_id);
                if ($ot && $ot->overtime_type === 'custom') {
                    $otStart = Carbon::parse($ot->start_datetime, $now->timezone);
                    $otEnd   = Carbon::parse($ot->end_datetime,   $now->timezone);

                    $checkIn = Carbon::createFromFormat('H:i:s', $openCustom->check_in, $now->timezone)
                        ->setDateFrom($otStart);

                    // clamp trong cửa sổ OT
                    $effStart = $checkIn->gt($otStart) ? $checkIn->copy() : $otStart->copy();
                    $effEnd   = $now->lt($otEnd) ? $now->copy() : $otEnd->copy();

                    $windowMinutes = max(0, $otStart->diffInMinutes($otEnd, false));
                    $otMinutes     = max(0, $effStart->diffInMinutes($effEnd, false));

                    // 70% thời lượng OT custom
                    if ($windowMinutes > 0 && $otMinutes < ($windowMinutes * 0.7)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Chưa đủ 70% thời lượng OT tuỳ chỉnh để chấm công ra.',
                        ], 422);
                    }

                    $late       = $checkIn->gt($otStart) ? $otStart->diffInMinutes($checkIn) : 0;
                    $earlyLeave = $now->lt($otEnd) ? $otEnd->diffInMinutes($now) : 0;

                    $openCustom->update([
                        'check_out'            => $now->toTimeString(),
                        'worked_hours'         => 0, // OT custom không tính giờ công chính
                        'overtime_hours'       => round($otMinutes / 60, 2),
                        'late_minutes'         => $late,
                        'early_leave_minutes'  => $earlyLeave,
                        'is_overtime'          => 1,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Kết thúc OT tuỳ chỉnh thành công.',
                    ]);
                }
            }

            // ====================================================
            // 2) TÌM SLOT CA CHÍNH (HÔM NAY/HÔM QUA) TRÙNG THỜI ĐIỂM HIỆN TẠI
            // ====================================================
            $matchedShift = null;

            foreach ($assignments as $a) {
                if (!$a->shift) continue;

                // Neo theo work_date của phân công để xử lý ca đêm
                $start = Carbon::parse($a->work_date . ' ' . $a->shift->start_time, $now->timezone);
                $end   = Carbon::parse($a->work_date . ' ' . $a->shift->end_time,   $now->timezone);
                if ($end->lessThanOrEqualTo($start)) {
                    $end->addDay(); // ca đêm
                }

                // Cho phép vào sớm 60' và checkout muộn 4h (an toàn)
                if ($now->between($start->copy()->subMinutes(60), $end->copy()->addHours(4))) {
                    $matchedShift = [
                        'assignment' => $a,
                        'start' => $start,
                        'end' => $end,
                    ];
                    break;
                }
            }

            // ====================================================
            // 2.a) CÓ CA CHÍNH -> CHECK-IN / CHECK-OUT
            // ====================================================
            if ($matchedShift) {
                $a = $matchedShift['assignment'];
                $slotStart = $matchedShift['start'];
                $slotEnd   = $matchedShift['end'];

                // Tìm bản ghi chấm công của đúng ca (theo work_date của phân công)
                $attendance = Attendance::where('employee_id', $employee->id)
                    ->where('work_date', $a->work_date)
                    ->where('shift_id', $a->shift_id)
                    ->first();

                // Nếu đã check-out rồi
                if ($attendance && $attendance->check_out) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn đã chấm công ra cho ca này rồi.',
                    ], 409);
                }

                // ====== CHECK-OUT CA CHÍNH (có thể kiêm luôn OT AFTER_SHIFT) ======
                if ($attendance) {
                    $checkIn = Carbon::createFromFormat('H:i:s', $attendance->check_in, $now->timezone)
                        ->setDateFrom($slotStart);
                    $checkOut = $now->copy();

                    $actualMinutes   = max(0, $checkIn->diffInMinutes($checkOut, false));
                    $expectedMinutes = max(0, $slotStart->diffInMinutes($slotEnd, false));

                    // 70% thời lượng ca
                    if ($expectedMinutes > 0 && $actualMinutes < ($expectedMinutes * 0.7)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Chưa đủ 70% thời lượng ca để chấm công ra.',
                        ], 422);
                    }

                    // Tìm OT after_shift (nếu có) ứng với ca này
                    $otAfter = $otList->first(function ($ot) use ($slotEnd) {
                        if ($ot->overtime_type !== 'after_shift') return false;
                        $st = Carbon::parse($ot->start_datetime)->timezone($slotEnd->timezone);
                        // đơn sau ca: start_datetime phải bằng giờ kết thúc ca (cho phép lệch nhỏ <= 5p)
                        return abs($st->diffInMinutes($slotEnd, false)) <= 5;
                    });

                    // Tính worked (trong khung ca) & overtime (sau ca, clamp tới end OT)
                    $workedMinutes = min($actualMinutes, $expectedMinutes);
                    $overtimeMinutes = 0;

                    if ($otAfter) {
                        $otEnd = Carbon::parse($otAfter->end_datetime, $now->timezone);
                        if ($checkOut->gt($slotEnd)) {
                            $maxWindow = max(0, $slotEnd->diffInMinutes($otEnd)); // tổng cửa sổ OT
                            $extra     = $slotEnd->diffInMinutes($checkOut);      // phần làm vượt sau ca
                            $overtimeMinutes = min($extra, $maxWindow);
                        }
                    } else {
                        // Không có đơn after_shift => không tính OT phần vượt
                        $overtimeMinutes = 0;
                    }

                    // Đi muộn / về sớm so với khung ca
                    $lateMinutes  = $checkIn->gt($slotStart) ? $slotStart->diffInMinutes($checkIn) : 0;
                    $earlyMinutes = $checkOut->lt($slotEnd) ? $slotEnd->diffInMinutes($checkOut) : 0;

                    $attendance->update([
                        'check_out'           => $checkOut->toTimeString(),
                        'worked_hours'        => round($workedMinutes / 60, 2),
                        'overtime_hours'      => round($overtimeMinutes / 60, 2),
                        'late_minutes'        => $lateMinutes,
                        'early_leave_minutes' => $earlyMinutes,
                        'is_overtime'         => 0,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Chấm công ra ca chính thành công' . ($overtimeMinutes > 0 ? ' (có OT sau ca).' : '.'),
                    ]);
                }

                // ====== CHECK-IN CA CHÍNH ======
                $late = $now->gt($slotStart) ? $slotStart->diffInMinutes($now) : 0;
                if ($late > 120) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn đã đến muộn quá 2 tiếng, không thể chấm công vào.',
                    ], 422);
                }

                Attendance::create([
                    'employee_id' => $employee->id,
                    'shift_id'    => $a->shift_id,
                    'work_date'   => $a->work_date,
                    'check_in'    => $now->toTimeString(),
                    'late_minutes'=> $late,
                    'is_overtime' => 0,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Chấm công vào ca chính thành công.',
                ]);
            }

            // ====================================================
            // 3) KHÔNG CÓ CA CHÍNH -> XỬ LÝ OT CUSTOM (CHECK-IN)
            // ====================================================
            $activeCustomOT = $otList->first(function ($ot) use ($now) {
                if ($ot->overtime_type !== 'custom') return false;
                $st = Carbon::parse($ot->start_datetime, $now->timezone);
                $en = Carbon::parse($ot->end_datetime,   $now->timezone);
                return $now->between($st->copy()->subMinutes(60), $en); // cho vào sớm 60'
            });

            if ($activeCustomOT) {
                // Không tạo trùng check-in OT custom cùng cửa sổ
                $already = Attendance::where('employee_id', $employee->id)
                    ->whereNull('shift_id')
                    ->where('is_overtime', 1)
                    ->where('overtime_request_id', $activeCustomOT->id)
                    ->whereNull('check_out')
                    ->first();

                if ($already) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn đã chấm công vào OT tuỳ chỉnh rồi, hãy chấm công ra khi kết thúc.',
                    ], 409);
                }

                Attendance::create([
                    'employee_id'         => $employee->id,
                    'shift_id'            => null,
                    'work_date'           => $activeCustomOT->work_date,
                    'check_in'            => $now->toTimeString(),
                    'late_minutes'        => 0, // cập nhật khi check-out
                    'is_overtime'         => 1,
                    'overtime_request_id' => $activeCustomOT->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Bắt đầu OT tuỳ chỉnh thành công.',
                ]);
            }

            // Không khớp ca/OT nào
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có ca làm hoặc tăng ca phù hợp để chấm công lúc này.',
            ], 422);
        }

        // Nếu không khớp được khuôn mặt nào
        return response()->json([
            'success' => false,
            'message' => 'Không nhận diện được khuôn mặt. Hãy thử lại với góc mặt rõ hơn.',
        ], 400);
    }

}


