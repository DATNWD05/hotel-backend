<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollExportController extends Controller
{
    public function exportExcel()
    {
        // Xử lý tháng năm linh hoạt
        $monthInput = request('month');
        $yearInput = request('year');

        if ($monthInput && str_contains($monthInput, '-')) {
            $monthString = $monthInput; // VD: "2025-07"
        } else {
            $month = str_pad($monthInput ?? now()->format('m'), 2, '0', STR_PAD_LEFT);
            $year = $yearInput ?? now()->format('Y');
            $monthString = "$year-$month";
        }

        $query = Payroll::with('employee')->where('month', 'like', "$monthString%");

        $payrolls = $query->get();

        if ($payrolls->isEmpty()) {
            return response()->json(['message' => 'Không có dữ liệu bảng lương phù hợp.'], 404);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // === DÒNG TIÊU ĐỀ LỚN ===
        $title = 'BẢNG LƯƠNG NHÂN VIÊN THÁNG ' . substr($monthString, 5, 2) . ' NĂM ' . substr($monthString, 0, 4);
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // === DÒNG TIÊU ĐỀ CỘT ===
        $headers = ['Mã NV', 'Tên nhân viên', 'Tháng', 'Tổng giờ', 'Số ngày công', 'Tổng lương', 'Thưởng', 'Phạt', 'Lương cuối'];
        $sheet->fromArray([$headers], null, 'A2');

        $sheet->getStyle('A2:I2')->applyFromArray([
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // === DỮ LIỆU ===
        $rows = $payrolls->map(function ($item) {
            return [
                $item->employee->employee_code ?? '',
                $item->employee->name ?? '',
                $item->month,
                $item->total_hours,
                $item->total_days,
                $item->total_salary,
                $item->bonus,
                $item->penalty,
                $item->final_salary,
            ];
        });

        $sheet->fromArray($rows->toArray(), null, 'A3');

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $rowCount = $payrolls->count() + 2;
        $sheet->getStyle("A3:I{$rowCount}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        foreach (['F', 'G', 'H', 'I'] as $col) {
            $sheet->getStyle("{$col}3:{$col}{$rowCount}")
                ->getNumberFormat()->setFormatCode('#,##0" đ"');
            $sheet->getStyle("{$col}3:{$col}{$rowCount}")
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $fileName = 'bang_luong_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment;filename=\"$fileName\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    public function exportPdf()
    {
        $monthInput = request('month');
        $yearInput = request('year');

        if ($monthInput && str_contains($monthInput, '-')) {
            $monthString = $monthInput;
        } else {
            $month = str_pad($monthInput ?? now()->format('m'), 2, '0', STR_PAD_LEFT);
            $year = $yearInput ?? now()->format('Y');
            $monthString = "$year-$month";
        }

        $query = Payroll::with('employee')->where('month', 'like', "$monthString%");

        $payrolls = $query->get();

        if ($payrolls->isEmpty()) {
            return response()->json(['message' => 'Không có dữ liệu bảng lương phù hợp.'], 404);
        }

        $pdf = Pdf::loadView('exports.payroll', [
            'payrolls' => $payrolls,
            'month' => substr($monthString, 5, 2),
            'year' => substr($monthString, 0, 4),
        ]);

        return $pdf->download("bang_luong_{$monthString}.pdf");
    }
}
