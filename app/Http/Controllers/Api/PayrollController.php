<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Http\Request;

class PayrollController extends Controller
{

    public function generate(Request $request)
    {
        $month = $request->input('month'); // ví dụ: 2025-07
        if (!$month) {
            return response()->json(['message' => 'Thiếu tháng'], 400);
        }

        $employees = Employee::all();

        foreach ($employees as $employee) {
            $attendances = Attendance::with('shift')
                ->where('employee_id', $employee->id)
                ->where('work_date', 'like', "$month%")
                ->get();

            $totalHours = 0;
            $totalSalary = 0;
            $totalDays = $attendances->count();

            foreach ($attendances as $a) {
                $hours = $a->worked_hours ?? 0;
                $rate = optional($a->shift)->hourly_rate ?? 0;
                $totalHours += $hours;
                $totalSalary += $hours * $rate;
            }

            Payroll::updateOrCreate(
                ['employee_id' => $employee->id, 'month' => $month],
                [
                    'total_hours' => $totalHours,
                    'total_days' => $totalDays,
                    'total_salary' => $totalSalary,
                    'bonus' => 0,
                    'penalty' => 0,
                    'final_salary' => $totalSalary,
                ]
            );
        }

        return response()->json(['message' => 'Tạo bảng lương thành công']);
    }


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


    public function show($id)
    {
        $payroll = Payroll::with('employee')->findOrFail($id);
        return response()->json($payroll);
    }
}
