<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\File;

class InvoiceController extends Controller
{

    // [2] API: Xem danh sách hóa đơn
    public function index()
    {
        return response()->json(Invoice::all());
    }

    // [3] API: Xem chi tiết hóa đơn
    public function show($id)
    {
        $invoice = Invoice::with('booking')->where("booking_id", "=", $id)->first();

        if (!$invoice) {
            return response()->json([
                'message' => 'Không tìm thấy hóa đơn.',
            ], 404);
        }

        return response()->json([
            'invoice_code'     => $invoice->invoice_code,
            'booking_id'       => $invoice->booking_id,
            'issued_date'      => $invoice->issued_date,
            'room_amount'      => $invoice->room_amount,
            'service_amount'   => $invoice->service_amount,
            'discount_amount'  => $invoice->discount_amount,
            'deposit_amount'   => $invoice->deposit_amount,
            'total_amount'     => $invoice->total_amount,
            'created_at'       => $invoice->created_at,
            'updated_at'       => $invoice->updated_at,
            'booking'          => $invoice->booking ?? null,
        ]);
    }

    public function printInvoice($id)
    {
        $invoice = Invoice::with('booking.customer')->find($id);

        if (!$invoice || !$invoice->booking || !$invoice->booking->customer) {
            return response()->json(['message' => 'Không tìm thấy thông tin khách hàng.'], 404);
        }

        // Tạo thư mục invoices nếu chưa có
        $directory = storage_path('app/public/invoices');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        // Tạo PDF hóa đơn và lưu
        $fileName = 'invoice_' . $invoice->id . '.pdf';
        $filePath = $directory . '/' . $fileName;
        $pdf = Pdf::loadView('invoices.pdf', compact('invoice'));
        $pdf->save($filePath);

        //Gửi email nếu có
        $email = $invoice->booking->customer->email ?? null;
        if ($email) {
            Mail::to($email)->send(new InvoiceMail($invoice, $filePath));
        }

        // Gửi lệnh in
        $this->printToPrinter($filePath);

        // Trả về URL public cho frontend
        $pdfUrl = asset('storage/invoices/' . $fileName);

        return response()->json([
            'message' => 'Đã gửi email và gửi lệnh in hóa đơn.',
            'invoice_code' => $invoice->invoice_code,
            'email' => $email,
            'pdf_url' => $pdfUrl
        ]);
    }

    // Hàm phụ dùng để in hóa đơn PDF
    private function printToPrinter($filePath)
    {
        if (!file_exists($filePath)) {
            Log::warning("Không tìm thấy file để in: $filePath");
            return;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // In bằng lệnh rundll32 (mở PDF bằng mặc định rồi in)
            $escapedPath = str_replace('/', '\\', $filePath);

            // Mở bằng phần mềm mặc định để in (Adobe Reader, Chrome, ...)
            $command = "start /min \"\" \"" . $escapedPath . "\"";

            pclose(popen("start /B cmd /C \"$command\"", "r"));
        }
    }
}
