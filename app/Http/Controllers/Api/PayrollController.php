<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\SalaryRule;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    /**
     * Tạo bảng lương cho toàn bộ nhân viên theo tháng.
     */
    public function generate(Request $request)
    {
        // Xử lý tháng và năm đầu vào
        $monthInput = $request->input('month'); // Có thể là "07" hoặc "2025-07"
        $yearInput = $request->input('year');   // Có thể có hoặc không

        if ($monthInput && str_contains($monthInput, '-')) {
            // Nếu người dùng truyền dạng "2025-07"
            $monthString = $monthInput;
        } else {
            // Nếu chỉ có "month" và "year" riêng lẻ hoặc không có gì
            $month = str_pad($monthInput ?? now()->format('m'), 2, '0', STR_PAD_LEFT);
            $year = $yearInput ?? now()->format('Y');
            $monthString = "$year-$month"; // Kết quả: "2025-07"
        }

        $employees = Employee::with('role')->get();

        foreach ($employees as $employee) {
            $rule = SalaryRule::where('role_id', $employee->role_id)->first();

            if (!$rule) {
                continue; // Bỏ qua nếu không có rule
            }

            $overtimeRate = $rule->overtime_multiplier;
            $latePenalty = $rule->late_penalty_per_minute;
            $earlyPenalty = $rule->early_leave_penalty_per_minute;
            $dailyAllowance = $rule->daily_allowance;

            $attendances = Attendance::with('shift')
                ->where('employee_id', $employee->id)
                ->where('work_date', 'like', "$monthString%")
                ->get();

            $totalHours = 0;
            $totalOvertime = 0;
            $totalDays = $attendances->count();
            $totalSalary = 0;
            $penalty = 0;

            foreach ($attendances as $a) {
                $hours = $a->worked_hours ?? 0;
                $overtime = $a->overtime_hours ?? 0;
                $rate = optional($a->shift)->hourly_rate ?? 0;

                $lateMinutes = $a->late_minutes ?? 0;
                $earlyLeaveMinutes = $a->early_leave_minutes ?? 0;

                $totalHours += $hours;
                $totalOvertime += $overtime;

                $totalSalary += ($hours * $rate) + ($overtime * $rate * $overtimeRate);
                $penalty += $lateMinutes * $latePenalty + $earlyLeaveMinutes * $earlyPenalty;
            }

            $bonus = $dailyAllowance * $totalDays;
            $finalSalary = $totalSalary + $bonus - $penalty;

            Payroll::updateOrCreate(
                ['employee_id' => $employee->id, 'month' => $monthString],
                [
                    'total_hours' => $totalHours + $totalOvertime,
                    'total_days' => $totalDays,
                    'total_salary' => round($totalSalary),
                    'bonus' => round($bonus),
                    'penalty' => round($penalty),
                    'final_salary' => round($finalSalary),
                ]
            );
        }

        return response()->json(['message' => "Tạo bảng lương cho tháng $monthString thành công"]);
    }


    /**
     * Lấy danh sách bảng lương theo tháng (có phân trang).
     */
    public function index(Request $request)
    {
        $month = $request->input('month');

        $query = Payroll::with('employee');

        if ($month) {
            $query->where('month', $month);
        }

        return response()->json([
            'data' => $query->orderBy('month', 'desc')->paginate(10),
        ]);
    }

    /**
     * Lấy chi tiết lương theo id.
     */
    public function show($id)
    {
        $payroll = Payroll::with('employee')->findOrFail($id);
        return response()->json($payroll);
    }
}
