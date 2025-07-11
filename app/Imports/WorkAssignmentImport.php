<?php

namespace App\Imports;

use App\Models\WorkAssignment;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class WorkAssignmentImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $validator = Validator::make($row->toArray(), [
                'employee_id' => 'required|exists:employees,id',
                'shift_id' => 'required|exists:shifts,id',
                'work_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                // Có thể ghi log hoặc bỏ qua dòng lỗi
                continue;
            }

            // Kiểm tra trùng lặp
            $exists = WorkAssignment::where('employee_id', $row['employee_id'])
                ->where('work_date', $row['work_date'])
                ->first();

            if (!$exists) {
                WorkAssignment::create([
                    'employee_id' => $row['employee_id'],
                    'shift_id' => $row['shift_id'],
                    'work_date' => $row['work_date'],
                ]);
            }
        }
    }
}
