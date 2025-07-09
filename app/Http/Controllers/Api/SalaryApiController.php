<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\Attendance;
use App\Models\Shift;
use App\Models\Salary;

class SalaryApiController extends Controller
{
    public function calculateMonthlySalary(Request $request)
    {
        $month = $request->input('month');
        $year = $request->input('year');

        if (!$month || !$year) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng nhập đầy đủ tháng và năm.'
            ], 422);
        }

        $employees = Employee::all();

        foreach ($employees as $employee) {
            $attendances = Attendance::where('employee_id', $employee->id)
                ->whereMonth('work_date', $month)
                ->whereYear('work_date', $year)
                ->get();

            $totalHours = 0;
            $totalSalary = 0;

            foreach ($attendances as $att) {
                $hours = $att->worked_hours ?? 0;
                $shift = Shift::find($att->shift_id);

                $allowance = 0;
                if ($shift && $shift->is_night_shift) {
                    $allowance = 30000; // phụ cấp ca đêm
                }

                $totalHours += $hours;
                $totalSalary += ($hours * $employee->salary_per_hour) + $allowance;
            }

            // Cập nhật hoặc tạo mới
            Salary::updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'month' => $month,
                    'year' => $year
                ],
                [
                    'total_hours' => $totalHours,
                    'total_salary' => $totalSalary
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => '✅ Tính lương thành công cho tất cả nhân viên.'
        ]);
    }
}
