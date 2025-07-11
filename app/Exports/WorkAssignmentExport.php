<?php

namespace App\Exports;

use App\Models\WorkAssignment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WorkAssignmentExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return WorkAssignment::select('employee_id', 'shift_id', 'work_date')->get();
    }

    public function headings(): array
    {
        return ['employee_id', 'shift_id', 'work_date'];
    }
}
