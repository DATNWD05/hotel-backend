<?php

namespace App\Imports;

use App\Models\WorkAssignment;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class WorkAssignmentImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        // Bỏ qua nếu thiếu thông tin
        if (!$row['employee_id'] || !$row['shift_id'] || !$row['work_date']) {
            return null;
        }

        // Bỏ qua nếu đã có phân công ngày đó
        if (WorkAssignment::where('employee_id', $row['employee_id'])
            ->where('work_date', $row['work_date'])
            ->exists()
        ) {
            return null;
        }

        return new WorkAssignment([
            'employee_id' => $row['employee_id'],
            'shift_id' => $row['shift_id'],
            'work_date' => $row['work_date'],
        ]);
    }
}
