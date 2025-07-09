<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attendance;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    // 🟢 API chấm công giờ vào
    public function checkIn(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'work_date' => 'required|date',
        ]);

        // Kiểm tra đã check-in chưa
        $exists = Attendance::where('employee_id', $request->employee_id)
            ->where('work_date', $request->work_date)
            ->where('shift_id', $request->shift_id)
            ->first();

        if ($exists) {
            return response()->json(['message' => 'Đã chấm công giờ vào cho ca này'], 400);
        }

        $checkInTime = now()->setTimezone('Asia/Ho_Chi_Minh')->format('H:i:s');

        $attendance = Attendance::create([
            'employee_id' => $request->employee_id,
            'shift_id' => $request->shift_id,
            'work_date' => $request->work_date,
            'check_in' => $checkInTime,
        ]);

        return response()->json([
            'message' => 'Chấm công giờ vào thành công',
            'data' => $attendance
        ]);
    }

    // 🟡 API chấm công giờ ra
    public function checkOut(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'shift_id' => 'required|exists:shifts,id',
            'work_date' => 'required|date',
        ]);

        $attendance = Attendance::where('employee_id', $request->employee_id)
            ->where('shift_id', $request->shift_id)
            ->where('work_date', $request->work_date)
            ->first();

        if (!$attendance) {
            return response()->json(['message' => 'Chưa check-in, không thể check-out'], 400);
        }

        if ($attendance->check_out) {
            return response()->json(['message' => 'Đã chấm công giờ ra'], 400);
        }

        try {
            $checkIn = Carbon::createFromFormat('H:i:s', $attendance->check_in);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Định dạng check-in không hợp lệ'], 400);
        }

        $checkOut = now()->setTimezone('Asia/Ho_Chi_Minh');
        $workedHours = $checkOut->diffInMinutes($checkIn) / 60;

        $attendance->update([
            'check_out' => $checkOut->format('H:i:s'),
            'worked_hours' => round($workedHours, 2),
        ]);

        return response()->json([
            'message' => 'Chấm công giờ ra thành công',
            'data' => $attendance
        ]);
    }
}
