<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\SalaryRule;
use App\Models\Role;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PayrollController extends Controller
{
    public function generate(Request $request)
    {
        // --- Chuẩn hóa tham số tháng ---
        $monthInput = $request->input('month');
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

        // === Bỏ qua role "owner" ===
        $ownerRoleIds = Role::query()
            ->whereRaw('LOWER(name) = ?', ['owner'])
            ->pluck('id')
            ->all();

        $employees = Employee::with('role')
            ->when(!empty($ownerRoleIds), fn($q) => $q->whereNotIn('role_id', $ownerRoleIds))
            ->get();

        foreach ($employees as $employee) {
            $rule = SalaryRule::where('role_id', $employee->role_id)->first();
            if (!$rule) continue;

            $overtimeRate   = (float) ($rule->overtime_multiplier ?? 1.5);
            $latePenalty    = (float) ($rule->late_penalty_per_minute ?? 0);
            $earlyPenalty   = (float) ($rule->early_leave_penalty_per_minute ?? 0);
            $dailyAllowance = (float) ($rule->daily_allowance ?? 0);

            $attendances = Attendance::with('shift')
                ->where('employee_id', $employee->id)
                ->whereBetween('work_date', [$start, $end])
                ->get();

            $totalHours       = 0.0;   // giờ làm chính
            $totalOvertime    = 0.0;   // giờ OT (từ attendances.overtime_hours)
            $baseSalary       = 0.0;   // lương cơ bản
            $overtimeSalary   = 0.0;   // lương OT
            $penalty          = 0.0;
            $workedDaysSet    = [];

            foreach ($attendances as $a) {
                $hours    = (float) ($a->worked_hours ?? 0);
                $overtime = (float) ($a->overtime_hours ?? 0);  // ✅ tận dụng cột trong attendances

                // chọn rate: employee > role > shift
                $empHourly  = $employee->hourly_rate ? (float) $employee->hourly_rate : null;
                $roleHourly = optional($rule)->hourly_rate ? (float) $rule->hourly_rate : null;
                $shiftRate  = optional($a->shift)->hourly_rate ? (float) $a->shift->hourly_rate : 0;

                $rate = $empHourly  > 0 ? $empHourly
                    : ($roleHourly > 0 ? $roleHourly
                        : ($shiftRate  > 0 ? $shiftRate : 0));

                $totalHours       += $hours;
                $totalOvertime    += $overtime;                     // ✅ cộng dồn giờ OT
                $baseSalary       += $hours * $rate;
                $overtimeSalary   += $overtime * $rate * $overtimeRate;  // ✅ tính lương OT

                $late  = (int) ($a->late_minutes ?? 0);
                $early = (int) ($a->early_leave_minutes ?? 0);
                $penalty += $late * $latePenalty + $early * $earlyPenalty;

                $dateKey = is_string($a->work_date)
                    ? substr($a->work_date, 0, 10)
                    : \Carbon\Carbon::parse($a->work_date)->toDateString();
                $workedDaysSet[$dateKey] = true;
            }

            $distinctDays = count($workedDaysSet);
            $bonus        = $dailyAllowance * $distinctDays;
            $finalSalary  = $baseSalary + $overtimeSalary + $bonus - $penalty;

            Payroll::updateOrCreate(
                ['employee_id' => $employee->id, 'month' => $monthString],
                [
                    'total_hours'     => (int) round($totalHours + $totalOvertime),
                    'overtime_hours'  => (float) round($totalOvertime, 2),   // ✅ lưu giờ OT
                    'total_days'      => $distinctDays,
                    'total_salary'    => (int) round($baseSalary),
                    'overtime_salary' => (int) round($overtimeSalary),       // ✅ lưu lương OT
                    'bonus'           => (int) round($bonus),
                    'penalty'         => (int) round($penalty),
                    'final_salary'    => (int) round($finalSalary),
                ]
            );
        }

        return response()->json(['message' => "Tạo bảng lương cho tháng $monthString thành công"]);
    }

    public function index(Request $request)
    {
        $month = $request->input('month')
            ?? Payroll::orderByDesc('month')->value('month')
            ?? now()->format('Y-m');

        // TRẢ VỀ TOÀN BỘ, KHÔNG PHÂN TRANG
        $data = Payroll::with('employee')
            ->where('month', $month)
            ->orderBy('employee_id')
            ->get();

        return response()->json([
            'data' => $data,
            'selected_month' => $month,
        ]);
    }


    public function show($id)
    {
        $payroll = Payroll::with('employee')->findOrFail($id);
        return response()->json($payroll);
    }
}
