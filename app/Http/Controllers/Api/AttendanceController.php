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

class AttendanceController extends Controller
{
    //API chấm công giờ vào
    // public function checkIn(Request $request)
    // {
    //     $request->validate([
    //         'employee_id' => 'required|exists:employees,id',
    //         'shift_id' => 'required|exists:shifts,id',
    //         'work_date' => 'required|date',
    //     ]);

    //     //Kiểm tra đã check-in chưa
    //     $exists = Attendance::where('employee_id', $request->employee_id)
    //         ->where('work_date', $request->work_date)
    //         ->where('shift_id', $request->shift_id)
    //         ->first();

    //     if ($exists) {
    //         return response()->json(['message' => 'Đã chấm công giờ vào cho ca này'], 400);
    //     }

    //     $checkInTime = now()->setTimezone('Asia/Ho_Chi_Minh')->format('H:i:s');

    //     $attendance = Attendance::create([
    //         'employee_id' => $request->employee_id,
    //         'shift_id' => $request->shift_id,
    //         'work_date' => $request->work_date,
    //         'check_in' => $checkInTime,
    //     ]);

    //     return response()->json([
    //         'message' => 'Chấm công giờ vào thành công',
    //         'data' => $attendance
    //     ]);
    // }

    //API chấm công giờ ra
    // public function checkOut(Request $request)
    // {
    //     $request->validate([
    //         'employee_id' => 'required|exists:employees,id',
    //         'shift_id' => 'required|exists:shifts,id',
    //         'work_date' => 'required|date',
    //     ]);

    //     $attendance = Attendance::where('employee_id', $request->employee_id)
    //         ->where('shift_id', $request->shift_id)
    //         ->where('work_date', $request->work_date)
    //         ->first();

    //     if (!$attendance) {
    //         return response()->json(['message' => 'Chưa check-in, không thể check-out'], 400);
    //     }

    //     if ($attendance->check_out) {
    //         return response()->json(['message' => 'Đã chấm công giờ ra'], 400);
    //     }

    //     try {
    //         $checkIn = Carbon::createFromFormat('H:i:s', $attendance->check_in);
    //     } catch (\Exception $e) {
    //         return response()->json(['message' => 'Định dạng check-in không hợp lệ'], 400);
    //     }

    //     $checkOut = now()->setTimezone('Asia/Ho_Chi_Minh');
    //     $workedHours = $checkOut->diffInMinutes($checkIn) / 60;

    //     $attendance->update([
    //         'check_out' => $checkOut->format('H:i:s'),
    //         'worked_hours' => round($workedHours, 2),
    //     ]);

    //     return response()->json([
    //         'message' => 'Chấm công giờ ra thành công',
    //         'data' => $attendance
    //     ]);
    // }

    public function index(Request $request)
    {
        $query = Attendance::with(['employee', 'shift']);

        //Lọc theo ngày
        if ($request->filled('from_date')) {
            $query->whereDate('work_date', '>=', $request->input('from_date'));
        }
        if ($request->filled('to_date')) {
            $query->whereDate('work_date', '<=', $request->input('to_date'));
        }

        //Lọc theo nhân viên
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        $perPage = $request->input('per_page', 10);
        $attendances = $query->orderByDesc('work_date')->paginate($perPage);

        // Định dạng dữ liệu trả về
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
                'overtime_hours' => round(($attendance->overtime_minutes ?? 0) / 60, 2),
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

        $base64Image = preg_replace('#^data:image/\w+;base64,#i', '', $request->input('image'));
        $base64Image = str_replace(' ', '+', $base64Image);
        Storage::put("debug_camera.jpg", base64_decode($base64Image));

        $detectResponse = Http::withoutVerifying()->asForm()->post('https://api-us.faceplusplus.com/facepp/v3/detect', [
            'api_key' => env('FACEPP_KEY'),
            'api_secret' => env('FACEPP_SECRET'),
            'image_base64' => $base64Image,
        ]);

        if (!$detectResponse->successful() || count($detectResponse['faces'] ?? []) === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Không phát hiện được khuôn mặt. Vui lòng chụp chính diện, đủ sáng.',
            ]);
        }

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

            if ($compareResponse->successful() && ($compareResponse['confidence'] ?? 0) >= 65) {
                $employee = $face->employee;
                $now = now();
                $today = $now->toDateString();
                $yesterday = $now->copy()->subDay()->toDateString();

                $assignment = WorkAssignment::where('employee_id', $employee->id)
                    ->whereIn('work_date', [$today, $yesterday])
                    ->orderByDesc('work_date')
                    ->first();

                if (!$assignment) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không được phân công làm việc hôm nay.',
                    ]);
                }

                $shift = Shift::find($assignment->shift_id);
                $shiftStart = Carbon::createFromFormat('H:i:s', $shift->start_time)
                    ->setDateFrom(Carbon::parse($assignment->work_date));
                $shiftEnd = Carbon::createFromFormat('H:i:s', $shift->end_time)
                    ->setDateFrom(Carbon::parse($assignment->work_date));

                if ($shiftEnd->lessThan($shiftStart)) {
                    $shiftEnd->addDay(); // ca qua đêm
                }

                $earlyWindow = $shiftStart->copy()->subMinutes(60);
                $lateWindow = $shiftEnd->copy()->addHours(4);

                $attendance = Attendance::where('employee_id', $employee->id)
                    ->where('work_date', $assignment->work_date)
                    ->first();

                // ==== CHECK-OUT ====
                if ($attendance) {
                    if ($attendance->check_out) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn đã chấm công ra hôm nay rồi.',
                        ]);
                    }

                    $checkIn = Carbon::createFromFormat('H:i:s', $attendance->check_in)->setDateFrom($shiftStart);
                    $checkOut = $now;

                    $actualMinutes = $checkIn->diffInMinutes($checkOut);
                    $shiftMinutes = $shiftStart->diffInMinutes($shiftEnd);

                    $workedMinutes = min($actualMinutes, $shiftMinutes);
                    $overtimeMinutes = max($actualMinutes - $shiftMinutes, 0);

                    $minWorkedMinutes = $shiftMinutes * 0.7;
                    if ($workedMinutes < $minWorkedMinutes) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Chưa đủ 70% thời gian làm việc để chấm công ra.',
                        ]);
                    }

                    // CHỈNH SỬA CHỖ NÀY: Chuyển đổi sang giờ
                    $workedHours = round($workedMinutes / 60, 2);
                    $overtimeHours = round($overtimeMinutes / 60, 2);

                    $earlyLeave = $shiftEnd->diffInMinutes($checkOut, false);
                    $earlyLeaveMinutes = $earlyLeave > 0 ? 0 : abs($earlyLeave);

                    $attendance->update([
                        'check_out' => $checkOut->toTimeString(),
                        'worked_hours' => $workedHours,
                        'early_leave_minutes' => $earlyLeaveMinutes,
                        'overtime_minutes' => $overtimeHours,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => "Chấm công ra thành công cho {$employee->name}. Giờ làm: {$workedHours}h, Tăng ca: {$overtimeHours}h",
                    ]);
                }

                // ==== CHECK-IN ====
                if (!$now->between($earlyWindow, $lateWindow)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Không nằm trong thời gian hợp lệ để chấm công vào.',
                    ]);
                }

                $lateMinutes = $now->gt($shiftStart) ? $shiftStart->diffInMinutes($now) : 0;
                if ($lateMinutes > 120) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn đã đến muộn quá 2 tiếng, không thể chấm công vào.',
                    ]);
                }

                Attendance::create([
                    'employee_id' => $employee->id,
                    'shift_id' => $shift->id,
                    'work_date' => $assignment->work_date,
                    'check_in' => $now->toTimeString(),
                    'late_minutes' => $lateMinutes,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Chấm công vào thành công cho {$employee->name}.",
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Không nhận diện được khuôn mặt. Hãy thử lại với góc mặt rõ hơn.',
        ]);
    }
}
