<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Models\Attendance;
use App\Models\EmployeeFace;
use Carbon\Carbon;

class FaceCheckInController extends Controller
{
    private function getShiftIdFromTime($now)
    {
        $shifts = \App\Models\Shift::all();

        foreach ($shifts as $shift) {
            $start = Carbon::createFromTimeString($shift->start_time);
            $end = Carbon::createFromTimeString($shift->end_time);
            $current = Carbon::createFromFormat('H:i:s', $now->format('H:i:s'));

            if ($end->lessThan($start)) $end->addDay(); // ca đêm

            if ($current->between($start, $end)) {
                return $shift->id;
            }
        }

        return null;
    }

    public function faceCheckIn(Request $request)
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

                $attendance = Attendance::with('shift')->where('employee_id', $employee->id)
                    ->where('work_date', $today)
                    ->first();

                if ($attendance) {
                    // Nếu đã check-out
                    if ($attendance->check_out) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bạn đã chấm công ra hôm nay rồi.',
                        ]);
                    }

                    // Tính giờ làm chính xác
                    $checkIn = Carbon::createFromFormat('H:i:s', $attendance->check_in);
                    $checkOut = $now;

                    $shift = $attendance->shift;
                    $shiftStart = Carbon::createFromTimeString($shift->start_time);
                    $shiftEnd = Carbon::createFromTimeString($shift->end_time);
                    if ($shiftEnd->lessThan($shiftStart)) $shiftEnd->addDay(); // ca đêm

                    // Giới hạn thời gian trong ca
                    if ($checkIn->lessThan($shiftStart)) $checkIn = $shiftStart;
                    if ($checkOut->greaterThan($shiftEnd)) $checkOut = $shiftEnd;

                    $workedMinutes = $checkIn->diffInMinutes($checkOut);
                    $workedHours = round($workedMinutes / 60, 2);

                    $attendance->update([
                        'check_out' => $now->toTimeString(),
                        'worked_hours' => $workedHours,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => "✅ Chấm công ra thành công cho {$employee->name}. Tổng giờ làm: {$workedHours}h",
                    ]);
                } else {
                    // Chấm công vào
                    $shiftId = $this->getShiftIdFromTime($now);
                    if (!$shiftId) {
                        return response()->json([
                            'success' => false,
                            'message' => '⛔ Không xác định được ca làm việc từ thời gian hiện tại.',
                        ]);
                    }

                    Attendance::create([
                        'employee_id' => $employee->id,
                        'shift_id' => $shiftId,
                        'work_date' => $today,
                        'check_in' => $now->toTimeString(),
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
            'message' => 'Không nhận diện được khuôn mặt. Hãy thử lại với góc mặt rõ hơn.',
        ]);
    }
}
