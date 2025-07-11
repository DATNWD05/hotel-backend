<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Attendance;
use App\Models\EmployeeFace;
use App\Models\Shift;
use App\Models\WorkAssignment;
use Carbon\Carbon;

class FaceCheckInController extends Controller
{
    public function faceCheckIn(Request $request)
    {
        $request->validate(['image' => 'required|string']);

        $base64Image = preg_replace('#^data:image/\w+;base64,#i', '', $request->input('image'));
        $base64Image = str_replace(' ', '+', $base64Image);
        Storage::put("debug_camera.jpg", base64_decode($base64Image));

        // ⚠️ Gửi tới API Face++ (BỎ QUA SSL)
        $detectResponse = Http::withoutVerifying()->asForm()->post('https://api-us.faceplusplus.com/facepp/v3/detect', [
            'api_key' => env('FACEPP_KEY'),
            'api_secret' => env('FACEPP_SECRET'),
            'image_base64' => $base64Image,
        ]);

        if (!$detectResponse->successful() || count($detectResponse['faces'] ?? []) === 0) {
            return response()->json([
                'success' => false,
                'message' => '❌ Không phát hiện được khuôn mặt. Vui lòng chụp chính diện, đủ sáng.',
            ]);
        }

        $faces = EmployeeFace::with('employee')->get();
        foreach ($faces as $face) {
            if (!Storage::disk('public')->exists($face->image_path)) continue;

            $faceBase64 = base64_encode(Storage::disk('public')->get($face->image_path));

            // ⚠️ Gửi so sánh Face++ (BỎ QUA SSL)
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

                $assignment = WorkAssignment::where('employee_id', $employee->id)
                    ->where('work_date', $today)
                    ->first();

                if (!$assignment) {
                    return response()->json([
                        'success' => false,
                        'message' => '⛔ Bạn không được phân công làm việc hôm nay.',
                    ]);
                }

                $shift = Shift::find($assignment->shift_id);
                $shiftStart = Carbon::createFromTimeString($shift->start_time);
                $shiftEnd = Carbon::createFromTimeString($shift->end_time);
                if ($shiftEnd->lessThan($shiftStart)) $shiftEnd->addDay();

                $earlyWindow = $shiftStart->copy()->subMinutes(15);
                $lateWindow = $shiftEnd->copy();

                $attendance = Attendance::where('employee_id', $employee->id)
                    ->where('work_date', $today)
                    ->first();

                if ($attendance) {
                    if ($attendance->check_out) {
                        return response()->json([
                            'success' => false,
                            'message' => '✅ Bạn đã chấm công ra hôm nay rồi.',
                        ]);
                    }

                    $checkIn = Carbon::createFromFormat('H:i:s', $attendance->check_in);
                    $checkOut = $now;

                    $totalShiftMinutes = $shiftStart->diffInMinutes($shiftEnd);
                    $minCheckOutTime = $checkIn->copy()->addMinutes($totalShiftMinutes * 0.8);
                    if ($checkOut->lt($minCheckOutTime)) {
                        return response()->json([
                            'success' => false,
                            'message' => '⛔ Chưa đủ 80% thời gian ca để chấm công ra.',
                        ]);
                    }

                    if ($checkOut->gt($shiftEnd)) $checkOut = $shiftEnd;

                    $workedMinutes = $checkIn->diffInMinutes($checkOut);
                    $workedHours = round($workedMinutes / 60, 2);

                    $earlyLeave = $shiftEnd->diffInMinutes($checkOut, false);
                    $earlyLeaveMinutes = $earlyLeave > 0 ? 0 : abs($earlyLeave);

                    $attendance->update([
                        'check_out' => $now->toTimeString(),
                        'worked_hours' => $workedHours,
                        'early_leave_minutes' => $earlyLeaveMinutes,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => "✅ Chấm công ra thành công cho {$employee->name}. Giờ làm: {$workedHours}h",
                    ]);
                } else {
                    // Check-in
                    if (!$now->between($earlyWindow, $lateWindow)) {
                        return response()->json([
                            'success' => false,
                            'message' => '⛔ Không nằm trong thời gian hợp lệ để chấm công vào.',
                        ]);
                    }

                    $lateMinutes = $now->gt($shiftStart) ? $shiftStart->diffInMinutes($now) : 0;

                    Attendance::create([
                        'employee_id' => $employee->id,
                        'shift_id' => $shift->id,
                        'work_date' => $today,
                        'check_in' => $now->toTimeString(),
                        'late_minutes' => $lateMinutes,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => "✅ Chấm công vào thành công cho {$employee->name}",
                    ]);
                }
            }
        }

        return response()->json([
            'success' => false,
            'message' => '⛔ Không nhận diện được khuôn mặt. Hãy thử lại với góc mặt rõ hơn.',
        ]);
    }
}
