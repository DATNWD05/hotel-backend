<?php

namespace App\Http\Controllers\Api;

use App\Models\Invoice;
use App\Mail\InvoiceMail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;

class InvoiceController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Invoice::class, 'invoice');
    }

    // [2] API: Xem danh sách hóa đơn
    public function index()
    {
        return response()->json(Invoice::all());
    }

    // [3] API: Xem chi tiết hóa đơn
    public function show($id)
    {
        // lấy theo booking_id
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

    public function printInvoice($booking_id)
    {
        $invoice = Invoice::with([
            'booking.customer',
            'booking.rooms.roomType',
            'booking.services'
        ])->where("booking_id", $booking_id)->first();

        if (!$invoice || !$invoice->booking || !$invoice->booking->customer) {
            return response()->json(['message' => 'Không tìm thấy thông tin khách hàng.'], 404);
        }

        $checkIn = Carbon::parse($invoice->booking->check_in_date);
        $checkOut = Carbon::parse($invoice->booking->check_out_date);

        $nights = $checkIn->diffInDays($checkOut, false);

        if ($nights <= 0) {
            return response()->json([
                'message' => 'Ngày trả phòng phải sau ngày nhận phòng.'
            ], 422);
        }

        $roomsWithTotals = $invoice->booking->rooms->map(function ($room) use ($nights) {
            $roomRate = $room->roomType->base_rate ?? 0;
            $room->nights = $nights;
            $room->room_total = $roomRate * $nights;
            return $room;
        });

        $invoice->booking->setRelation('rooms', $roomsWithTotals);

        $invoice->formatted_checkin = $invoice->booking->check_in_date
            ? Carbon::parse($invoice->booking->check_in_date)->format('d/m/Y') : null;

        $invoice->formatted_checkout = $invoice->booking->check_out_date
            ? Carbon::parse($invoice->booking->check_out_date)->format('d/m/Y') : null;

        $invoice->formatted_issued = $invoice->issued_date
            ? Carbon::parse($invoice->issued_date)->format('d/m/Y') : null;

        $directory = storage_path('app/public/invoices');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $fileName = 'invoice_' . $invoice->id . '.pdf';
        $filePath = $directory . '/' . $fileName;

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'nights' => $nights
        ]);
        $pdf->save($filePath);

        $email = $invoice->booking->customer->email ?? null;
        if ($email) {
            try {
                Mail::to($email)->send(new InvoiceMail($invoice, $filePath));
            } catch (\Exception $e) {
                Log::error('Lỗi gửi mail: ' . $e->getMessage());
            }
        }

        $this->printToPrinter($filePath);

        $pdfUrl = asset('storage/invoices/' . $fileName);

        return response()->json([
            'message' => 'Đã gửi email và gửi lệnh in hóa đơn.',
            'invoice_code' => $invoice->invoice_code,
            'email' => $email,
            'pdf_url' => $pdfUrl
        ]);
    }

    private function printToPrinter($filePath)
    {
        if (!file_exists($filePath)) {
            Log::warning("Không tìm thấy file để in: $filePath");
            return;
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $escapedPath = str_replace('/', '\\', $filePath);
            $command = "start /min \"\" \"" . $escapedPath . "\"";
            pclose(popen("start /B cmd /C \"$command\"", "r"));
        } elseif (PHP_OS === 'Linux') {
            exec("lp " . escapeshellarg($filePath));
        }
    }
}
