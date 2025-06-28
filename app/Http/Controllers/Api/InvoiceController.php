<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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
        $invoice = Invoice::with('booking')->find($id);

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

        $pdf = Pdf::loadView('invoices.pdf', compact('invoice'));
        $filePath = storage_path('app/public/invoice_' . $invoice->id . '.pdf');
        $pdf->save($filePath);

        $email = $invoice->booking->customer->email ?? null;

        if (!$email) {
            return response()->json(['message' => 'Khách hàng chưa có email.'], 400);
        }

        // Gửi mail kèm file
        Mail::to($email)->send(new InvoiceMail($invoice, $filePath));

        return response()->json([
            'message' => 'Hóa đơn đã được gửi đến email khách hàng.',
            'invoice_code' => $invoice->invoice_code,
            'email' => $email
        ]);
    }
}
