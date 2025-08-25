<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\BookingRoomAmenity;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    // use AuthorizesRequests;

    // public function __construct()
    // {
    //     $this->authorizeResource(Invoice::class, 'invoice');
    // }

    /**
     * [GET] /invoices
     * Danh sách hóa đơn (có thể đổi sang paginate nếu cần).
     */
    public function index(): JsonResponse
    {
        $invoices = Invoice::with(['booking.customer'])
            ->orderByDesc('issued_date')
            ->get();

        return response()->json($invoices);
    }

    /**
     * [GET] /invoices/{invoice}
     * Xem chi tiết hóa đơn theo ID (route model binding).
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load([
            'booking.customer',
            'booking.rooms.roomType',
            'booking.services',
        ]);

        // Trả payload chi tiết (giờ/đêm, dịch vụ, tiện nghi, totals)
        $payload = $this->makeInvoicePayload($invoice);

        return response()->json($payload);
    }

    public function printInvoice($booking_id): JsonResponse
    {
        $invoice = Invoice::with([
            'booking.customer',
            'booking.rooms.roomType',
            'booking.services',
            'booking.rooms.bookingAmenities' => function ($q) use ($booking_id) {
                $q->wherePivot('booking_id', $booking_id);
            },
        ])->where('booking_id', $booking_id)->first();


        if (!$invoice || !$invoice->booking || !$invoice->booking->customer) {
            return response()->json(['message' => 'Không tìm thấy thông tin khách hàng.'], 404);
        }

        // Gom dữ liệu cho view (giờ/đêm + dòng chi tiết + totals đã chốt)
        $payload = $this->makeInvoicePayload($invoice);

        // Render PDF
        try {
            $pdf = Pdf::loadView('invoices.pdf', [
                'invoice' => $invoice,
                'payload' => $payload,
            ]);
        } catch (\Throwable $e) {
            Log::error('Lỗi render PDF hóa đơn: ' . $e->getMessage());
            return response()->json(['message' => 'Không thể tạo PDF hóa đơn.'], 500);
        }

        // Lưu file vào public disk để có URL /storage/...
        try {
            $relativePath = 'invoices/invoice_' . $invoice->id . '.pdf';
            Storage::disk('public')->put($relativePath, $pdf->output());
            $pdfUrl = Storage::url($relativePath);
            $absPath = Storage::disk('public')->path($relativePath);
        } catch (\Throwable $e) {
            Log::error('Lỗi lưu file PDF hóa đơn: ' . $e->getMessage());
            return response()->json(['message' => 'Không thể lưu PDF hóa đơn.'], 500);
        }

        // Gửi email (nếu có)
        $email = $invoice->booking->customer->email ?? null;
        if ($email) {
            try {
                Mail::to($email)->send(new InvoiceMail($invoice, $absPath));
            } catch (\Throwable $e) {
                Log::error('Lỗi gửi mail hóa đơn: ' . $e->getMessage());
            }
        }

        // In ra máy in cục bộ (nếu bật ENV PRINT_INVOICE=true)
        $this->printToPrinter($absPath);

        return response()->json([
            'message'      => 'Đã tạo PDF, gửi mail (nếu có) và gửi lệnh in.',
            'invoice_id'   => $invoice->id,
            'invoice_code' => $invoice->invoice_code,
            'email'        => $email,
            'pdf_url'      => $pdfUrl,
        ]);
    }

    /**
     * Gom dữ liệu hóa đơn theo giờ/đêm để trả JSON & đưa vào PDF view.
     * Trả về mảng:
     * - meta (is_hourly, duration_label/value, formatted dates)
     * - room_lines, service_lines, amenity_lines
     * - totals.saved (room_amount, service_amount, amenity_amount, discount, deposit, final_amount)
     * - booking/customer summary
     */
    private function makeInvoicePayload(Invoice $invoice): array
    {
        $booking = $invoice->booking;

        // Chuẩn hoá mốc thời gian cho hiển thị
        $ciRaw = $booking->check_in_date ?? $booking->check_in_at ?? $booking->start_date ?? $booking->arrival_at;
        $coRaw = $booking->check_out_date ?? $booking->check_out_at ?? $booking->end_date ?? $booking->departure_at;

        $checkIn  = $ciRaw ? Carbon::parse($ciRaw) : null;
        $checkOut = $coRaw ? Carbon::parse($coRaw) : null;

        $isHourly = (int)($booking->is_hourly ?? 0) === 1;
        $durationLabel = $isHourly ? 'hours' : 'nights';

        // ===== Phòng =====
        $roomLines = [];
        $roomTotal = 0.0;

        if ($isHourly) {
            // Giữ giờ thực, làm tròn lên, tối thiểu 1 giờ
            $start = $checkIn ? $checkIn->copy()->startOfMinute() : now()->startOfMinute();
            $end   = $checkOut ? $checkOut->copy()->startOfMinute() : now()->startOfMinute();
            $minutes = $start->diffInMinutes($end, false);
            $hours = max(1, (int)ceil(max(0, $minutes) / 60));

            foreach ($booking->rooms as $room) {
                $rate  = (float)($room->roomType->hourly_rate ?? 0);
                $total = round($rate * $hours, 0);
                $roomTotal += $total;

                $roomLines[] = [
                    'room_id'     => $room->id,
                    'room_number' => $room->room_number,
                    'unit'        => 'hour',
                    'unit_count'  => $hours,
                    'base_rate'   => $rate,
                    'total'       => $total,
                    'room_type'   => $room->roomType->name ?? null,
                ];
            }
            $durationValue = $hours;
        } else {
            // Theo đêm: tính từ startOfDay, tối thiểu 1 đêm
            $ci = $checkIn ? $checkIn->copy()->startOfDay() : now()->startOfDay();
            $co = $checkOut ? $checkOut->copy()->startOfDay() : now()->copy()->addDay()->startOfDay();
            if ($co->lt($ci)) {
                $co = $ci->copy()->addDay(); // dữ liệu sai → ép 1 đêm cho an toàn
            }
            $nights = max(1, $ci->diffInDays($co));

            foreach ($booking->rooms as $room) {
                $rate  = (float)($room->roomType->base_rate ?? 0);
                $total = round($rate * $nights, 0);
                $roomTotal += $total;

                $roomLines[] = [
                    'room_id'     => $room->id,
                    'room_number' => $room->room_number,
                    'unit'        => 'night',
                    'unit_count'  => $nights,
                    'base_rate'   => $rate,
                    'total'       => $total,
                    'room_type'   => $room->roomType->name ?? null,
                ];
            }
            $durationValue = $nights;
        }

        // ===== Dịch vụ =====
        $serviceLines = [];
        $serviceTotal = 0.0;
        foreach ($booking->services as $service) {
            $qty  = (int)($service->pivot->quantity ?? 1);
            $unit = isset($service->pivot->price) ? (float)$service->pivot->price : (float)($service->price ?? 0);
            $line = round($unit * $qty, 0);
            $serviceTotal += $line;

            $serviceLines[] = [
                'service_id' => $service->id,
                'name'       => $service->name,
                'price'      => $unit,
                'quantity'   => $qty,
                'total'      => $line,
            ];
        }

        // ===== Tiện nghi phát sinh =====
        $amenityRows = BookingRoomAmenity::with(['room:id,room_number', 'amenity:id,name,price'])
            ->where('booking_id', $booking->id)
            ->get();

        $amenityLines = [];
        $amenityTotal = 0.0;
        foreach ($amenityRows as $row) {
            $qty  = (int)($row->quantity ?? 1);
            $unit = is_null($row->price) ? (float)optional($row->amenity)->price : (float)$row->price;
            $line = round($unit * $qty, 0);
            $amenityTotal += $line;

            $amenityLines[] = [
                'room_id'      => $row->room_id,
                'room_number'  => optional($row->room)->room_number,
                'amenity_id'   => $row->amenity_id,
                'amenity_name' => optional($row->amenity)->name,
                'price'        => $unit,
                'quantity'     => $qty,
                'total'        => $line,
            ];
        }

        // ===== Tổng hợp (ưu tiên số đã lưu trong invoices – chốt sổ) =====
        $discount      = (float)($invoice->discount_amount ?? 0);
        $depositAmount = (float)($invoice->deposit_amount  ?? 0);

        $roomAmountSaved     = (float)($invoice->room_amount    ?? 0);
        $serviceAmountSaved  = (float)($invoice->service_amount ?? 0);
        $amenityAmountSaved  = (float)($invoice->amenity_amount ?? 0); // cột đã có trong DB
        $rawTotalSaved       = round($roomAmountSaved + $serviceAmountSaved + $amenityAmountSaved, 0);
        $finalAmountSaved    = (float)($invoice->total_amount ?? ($rawTotalSaved - $discount - $depositAmount));

        return [
            'invoice' => [
                'id'           => $invoice->id,
                'invoice_code' => $invoice->invoice_code,
                'issued_date'  => $invoice->issued_date ? Carbon::parse($invoice->issued_date)->format('Y-m-d H:i:s') : null,
                'created_at'   => $invoice->created_at ? Carbon::parse($invoice->created_at)->format('Y-m-d H:i:s') : null,
                'updated_at'   => $invoice->updated_at ? Carbon::parse($invoice->updated_at)->format('Y-m-d H:i:s') : null,
            ],

            'meta' => [
                'is_hourly'        => $isHourly ? 1 : 0,
                'duration_label'   => $durationLabel,   // 'hours' | 'nights'
                'duration_value'   => $durationValue,
                'formatted_checkin'  => $checkIn  ? $checkIn->format('d/m/Y H:i') : null,
                'formatted_checkout' => $checkOut ? $checkOut->format('d/m/Y H:i') : null,
                'formatted_issued'   => $invoice->issued_date ? Carbon::parse($invoice->issued_date)->format('d/m/Y') : null,
            ],

            'booking' => [
                'id'       => $booking->id,
                'status'   => $booking->status,
                'customer' => [
                    'name'  => $booking->customer->name  ?? null,
                    'email' => $booking->customer->email ?? null,
                    'phone' => $booking->customer->phone ?? null,
                ],
            ],

            'room_lines'    => $roomLines,
            'service_lines' => $serviceLines,
            'amenity_lines' => $amenityLines,

            'totals' => [
                // tham khảo (tính theo dữ liệu hiện thời)
                'calc' => [
                    'room_total'    => $roomTotal,
                    'service_total' => $serviceTotal,
                    'amenity_total' => $amenityTotal,
                    'raw_total'     => round($roomTotal + $serviceTotal + $amenityTotal, 0),
                ],
                // số đã lưu trong bảng invoices (chốt sổ)
                'saved' => [
                    'room_amount'     => $roomAmountSaved,
                    'service_amount'  => $serviceAmountSaved,
                    'amenity_amount'  => $amenityAmountSaved,
                    'discount_amount' => $discount,
                    'deposit_amount'  => $depositAmount,
                    'raw_total'       => $rawTotalSaved,
                    'final_amount'    => $finalAmountSaved,
                ],
            ],
        ];
    }

    /**
     * Gửi file tới máy in cục bộ nếu bật ENV PRINT_INVOICE=true.
     */
    private function printToPrinter(string $absPath): void
    {
        if (!file_exists($absPath)) {
            Log::warning("Không tìm thấy file để in: {$absPath}");
            return;
        }

        if (!filter_var(env('PRINT_INVOICE', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $escaped = str_replace('/', '\\', $absPath);
                pclose(popen('start /B "" "' . $escaped . '"', 'r'));
            } else {
                exec('lp ' . escapeshellarg($absPath) . ' > /dev/null 2>&1 &');
            }
        } catch (\Throwable $e) {
            Log::error('Lỗi gửi lệnh in: ' . $e->getMessage());
        }
    }
}
