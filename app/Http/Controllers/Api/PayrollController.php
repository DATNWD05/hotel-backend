<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\SalaryRule;
use App\Models\Role;               // <-- THÊM DÒNG NÀY
use Illuminate\Http\Request;
use Carbon\Carbon;

class PayrollController extends Controller
{
    /**
     * Tạo bảng lương cho toàn bộ nhân viên theo tháng.
     * - Ưu tiên rate: employee.hourly_rate > salary_rules.hourly_rate > shifts.hourly_rate
     * - Phụ cấp theo số ngày làm việc DISTINCT
     * - BỎ QUA role = owner
     */
    public function generate(Request $request)
    {
        // --- Chuẩn hóa tham số tháng ---
        $monthInput = $request->input('month'); // "07" hoặc "2025-07"
        $yearInput  = $request->input('year');

        if ($monthInput && str_contains($monthInput, '-')) {
            [$year, $month] = explode('-', $monthInput);
        } else {
            $year  = $yearInput  ?? now()->format('Y');
            $month = str_pad($monthInput ?? now()->format('m'), 2, '0', STR_PAD_LEFT);
        }

        $monthString = "$year-$month";
        $start = Carbon::createFromFormat('Y-m-d', "$monthString-01")->startOfMonth();
        $end   = (clone $start)->endOfMonth();

        // === Lấy danh sách role_id là "owner" để loại bỏ ===
        $ownerRoleIds = Role::query()
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) = ?', ['owner']);
            })
            ->pluck('id')
            ->all();

        // Lấy nhân viên, BỎ QUA owner (và có thể filter active nếu có cột status)
        $employees = Employee::with('role')
            // ->where('status', 'active')
            ->when(!empty($ownerRoleIds), function ($q) use ($ownerRoleIds) {
                $q->whereNotIn('role_id', $ownerRoleIds);
            })
            ->get();

        foreach ($employees as $employee) {
            $rule = SalaryRule::where('role_id', $employee->role_id)->first();
            if (!$rule) {
                // Không có rule => bỏ qua nhân viên này
                continue;
            }

            $overtimeRate   = (float) ($rule->overtime_multiplier ?? 1.5);
            $latePenalty    = (float) ($rule->late_penalty_per_minute ?? 0);
            $earlyPenalty   = (float) ($rule->early_leave_penalty_per_minute ?? 0);
            $dailyAllowance = (float) ($rule->daily_allowance ?? 0);

            // Lấy chấm công trong tháng (kèm shift để có rate theo ca)
            $attendances = Attendance::with('shift')
                ->where('employee_id', $employee->id)
                ->whereBetween('work_date', [$start, $end])
                ->get();

            $totalHours    = 0.0;
            $totalOvertime = 0.0;
            $totalSalary   = 0.0;
            $penalty       = 0.0;
            $workedDaysSet = []; // key = YYYY-MM-DD

            foreach ($attendances as $a) {
                $hours    = (float) ($a->worked_hours ?? 0);
                $overtime = (float) ($a->overtime_hours ?? 0);

                // Ưu tiên rate: emp > role > shift, bỏ qua <= 0
                $empHourly  = is_null($employee->hourly_rate) ? null : (float) $employee->hourly_rate;
                $roleHourly = is_null(optional($rule)->hourly_rate) ? null : (float) optional($rule)->hourly_rate;
                $shiftRate  = (float) (optional($a->shift)->hourly_rate ?? 0);

                $rate = $empHourly  > 0 ? $empHourly
                      : ($roleHourly > 0 ? $roleHourly
                      : ($shiftRate  > 0 ? $shiftRate : 0));

                $lateMinutes       = (int) ($a->late_minutes ?? 0);
                $earlyLeaveMinutes = (int) ($a->early_leave_minutes ?? 0);

                $totalHours    += $hours;
                $totalOvertime += $overtime;

                $totalSalary   += ($hours * $rate) + ($overtime * $rate * $overtimeRate);
                $penalty       += $lateMinutes * $latePenalty + $earlyLeaveMinutes * $earlyPenalty;

                // Ghi nhận ngày làm việc DISTINCT
                $dateKey = is_string($a->work_date)
                    ? substr($a->work_date, 0, 10)
                    : Carbon::parse($a->work_date)->toDateString();
                $workedDaysSet[$dateKey] = true;
            }

            $distinctDays = count($workedDaysSet);
            $bonus        = $dailyAllowance * $distinctDays;
            $finalSalary  = $totalSalary + $bonus - $penalty;

            // Lưu (VND số nguyên). Đổi thành round(...,2) nếu muốn lẻ.
            Payroll::updateOrCreate(
                ['employee_id' => $employee->id, 'month' => $monthString],
                [
                    'total_hours'  => (int) round($totalHours + $totalOvertime),
                    'total_days'   => $distinctDays,
                    'total_salary' => (int) round($totalSalary),
                    'bonus'        => (int) round($bonus),
                    'penalty'      => (int) round($penalty),
                    'final_salary' => (int) round($finalSalary),
                ]
            );
        }

        return response()->json(['message' => "Tạo bảng lương cho tháng $monthString thành công (đã bỏ qua role owner)"]);
    }

    /**
     * Lấy danh sách bảng lương theo tháng (có phân trang).
     */
    public function index(Request $request)
    {
        $month = $request->input('month');

        if (!$month) {
            $month = Payroll::orderByDesc('month')->value('month')
                  ?? now()->format('Y-m');
        }

        $data = Payroll::with('employee')
            ->where('month', $month)
            ->orderBy('employee_id')
            ->paginate(10);

        return response()->json([
            'data' => $data,
            'selected_month' => $month,
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
