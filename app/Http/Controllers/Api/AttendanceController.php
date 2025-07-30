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

            if ($compareResponse->successful() && ($compareResponse['confidence'] ?? 0) >= 85) {
                $employee = $face->employee;
                $now = now();
                $today = $now->toDateString();

                // Lấy danh sách ca chính
                $assignments = WorkAssignment::with('shift')
                    ->where('employee_id', $employee->id)
                    ->where('work_date', $today)
                    ->get();

                // Lấy thông tin tăng ca nếu có
                $overtime = OvertimeRequest::where('employee_id', $employee->id)
                    ->where('work_date', $today)
                    ->first();

                // Dò thời gian hợp lệ checkin/check-out
                $matchedSlot = null;
                foreach ($assignments as $a) {
                    $start = Carbon::createFromFormat('H:i:s', $a->shift->start_time)->setDateFrom($now);
                    $end = Carbon::createFromFormat('H:i:s', $a->shift->end_time)->setDateFrom($now);
                    if ($end->lessThan($start)) $end->addDay();

                    if ($now->between($start->copy()->subMinutes(60), $end->copy()->addHours(4))) {
                        $matchedSlot = [
                            'start' => $start,
                            'end' => $end,
                            'shift_id' => $a->shift_id,
                            'type' => 'shift'
                        ];
                        break;
                    }
                }

                // Nếu không có ca chính phù hợp thì kiểm tra tăng ca
                if (!$matchedSlot && $overtime) {
                    $start = Carbon::createFromFormat('H:i', $overtime->start_time)->setDateFrom($now);
                    $end = Carbon::createFromFormat('H:i', $overtime->end_time)->setDateFrom($now);

                    if ($now->between($start->copy()->subMinutes(60), $end->copy()->addHours(4))) {
                        $matchedSlot = [
                            'start' => $start,
                            'end' => $end,
                            'shift_id' => null,
                            'type' => 'overtime'
                        ];
                    }
                }

                if (!$matchedSlot) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn không có ca làm hoặc tăng ca phù hợp để chấm công lúc này.',
                    ]);
                }

                // Tìm hoặc tạo chấm công
                $attendance = Attendance::where('employee_id', $employee->id)
                    ->where('work_date', $today)
                    ->where('shift_id', $matchedSlot['shift_id']) // null cho tăng ca
                    ->first();

                if ($attendance && $attendance->check_out) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn đã chấm công ra rồi.',
                    ]);
                }

                if ($attendance) {
                    // CHECK-OUT
                    $checkIn = Carbon::createFromFormat('H:i:s', $attendance->check_in)->setDateFrom($matchedSlot['start']);
                    $checkOut = $now;

                    $actualMinutes = $checkIn->diffInMinutes($checkOut);
                    $expectedMinutes = $matchedSlot['start']->diffInMinutes($matchedSlot['end']);

                    $workedMinutes = min($actualMinutes, $expectedMinutes);
                    $overtimeMinutes = max($actualMinutes - $expectedMinutes, 0);

                    if ($workedMinutes < $expectedMinutes * 0.7) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Chưa đủ 70% thời gian để chấm công ra.',
                        ]);
                    }

                    $attendance->update([
                        'check_out' => $checkOut->toTimeString(),
                        'worked_hours' => round($workedMinutes / 30) * 0.5,
                        'early_leave_minutes' => max(0, $matchedSlot['end']->diffInMinutes($checkOut, false) < 0 ? abs($matchedSlot['end']->diffInMinutes($checkOut)) : 0),
                        'overtime_hours' => round($overtimeMinutes / 30) * 0.5,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => "Chấm công ra thành công cho {$employee->name}",
                    ]);
                }

                // CHECK-IN
                $lateMinutes = $now->gt($matchedSlot['start']) ? $matchedSlot['start']->diffInMinutes($now) : 0;
                if ($lateMinutes > 120) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Bạn đã đến muộn quá 2 tiếng, không thể chấm công vào.',
                    ]);
                }

                Attendance::create([
                    'employee_id' => $employee->id,
                    'shift_id' => $matchedSlot['shift_id'],
                    'work_date' => $today,
                    'check_in' => $now->toTimeString(),
                    'late_minutes' => $lateMinutes,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => "Chấm công vào thành công cho {$employee->name}",
                ]);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Không nhận diện được khuôn mặt. Hãy thử lại với góc mặt rõ hơn.',
        ]);
    }
}
