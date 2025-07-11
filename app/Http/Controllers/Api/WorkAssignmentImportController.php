<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Imports\WorkAssignmentImport;
use Maatwebsite\Excel\Facades\Excel;

class WorkAssignmentImportController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            Excel::import(new WorkAssignmentImport, $request->file('file'));
            return response()->json([
                'success' => true,
                'message' => '✅ Import phân công thành công.',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '❌ Lỗi khi import: ' . $e->getMessage(),
            ], 500);
        }
    }
}
