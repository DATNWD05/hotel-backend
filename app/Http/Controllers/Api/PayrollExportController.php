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
        $month = request('month') ?? now()->format('m');
        $year = request('year') ?? now()->format('Y');

        $query = Payroll::with('employee');

        if ($month && $year) {
            $month = str_pad($month, 2, '0', STR_PAD_LEFT); // 7 => 07
            $query->where('month', 'like', "{$year}-{$month}%");
        }


        $payrolls = $query->get();

        if ($payrolls->isEmpty()) {
            return response()->json(['message' => 'Không có dữ liệu bảng lương phù hợp.'], 404);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // === DÒNG TIÊU ĐỀ LỚN ===
        $title = 'BẢNG LƯƠNG NHÂN VIÊN';
        if ($month && $year) {
            $title .= ' THÁNG ' . $month . ' NĂM ' . $year;
        }
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // === DÒNG TIÊU ĐỀ CỘT ===
        $headers = ['Mã NV', 'Tên nhân viên', 'Tháng', 'Tổng giờ', 'Số ngày công', 'Tổng lương', 'Thưởng', 'Phạt', 'Lương cuối'];
        $sheet->fromArray([$headers], null, 'A2');

        $styleHeader = [
            'font' => ['bold' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A2:I2')->applyFromArray($styleHeader);

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

        // === AUTO WIDTH CỘT ===
        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // === BORDER + ĐỊNH DẠNG TIỀN TỆ ===
        $rowCount = $payrolls->count() + 2; // vì bắt đầu từ dòng 3
        $sheet->getStyle("A3:I{$rowCount}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Định dạng tiền cho cột: F, G, H, I
        foreach (['F', 'G', 'H', 'I'] as $col) {
            $sheet->getStyle("{$col}3:{$col}{$rowCount}")
                ->getNumberFormat()
                ->setFormatCode('#,##0" đ"');
            $sheet->getStyle("{$col}3:{$col}{$rowCount}")
                ->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        // === EXPORT FILE ===
        $fileName = 'bang_luong_' . now()->format('Ymd_His') . '.xlsx';
        $writer = new Xlsx($spreadsheet);

        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', "attachment;filename=\"$fileName\"");
        $response->headers->set('Cache-Control', 'max-age=0');

        return $response;
    }

    public function exportPdf()
    {
        $month = request('month') ?? now()->format('m');
        $year = request('year') ?? now()->format('Y');

        $query = Payroll::with('employee');

        if ($month && $year) {
            $month = str_pad($month, 2, '0', STR_PAD_LEFT); // 7 => 07
            $query->where('month', 'like', "{$year}-{$month}%");
        }

        $payrolls = $query->get();

        if ($payrolls->isEmpty()) {
            return response()->json(['message' => 'Không có dữ liệu bảng lương phù hợp.'], 404);
        }

        $pdf = Pdf::loadView('exports.payroll', [
            'payrolls' => $payrolls,
            'month' => $month,
            'year' => $year,
        ]);

        return $pdf->download("bang_luong_{$month}_{$year}.pdf");
    }
}
