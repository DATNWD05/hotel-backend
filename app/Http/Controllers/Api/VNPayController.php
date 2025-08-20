<?php

namespace App\Http\Controllers\Api;

use App\Models\Booking;
use App\Models\BookingRoomAmenity;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;

class VNPayController extends Controller
{
    /**
     * Tính tổng tiền của booking (phòng, dịch vụ, tiện nghi), giảm giá, đặt cọc.
     * - Services: từ quan hệ $booking->services (pivot quantity, có thể có pivot->price)
     * - Amenities: từ bảng booking_room_amenities (fallback về giá trong bảng amenities nếu row->price null)
     * Trả về mảng chi tiết + tổng tiền cuối cùng.
     */
    private function calculateBookingTotals(Booking $booking): array
    {
        $booking->loadMissing(['rooms.roomType', 'services']);

        $isHourly = (int)($booking->is_hourly ?? 0) === 1;

        if ($isHourly) {
            // ----- Theo giờ -----
            $start = $booking->check_in_at
                ? Carbon::parse($booking->check_in_at)->startOfMinute()
                : Carbon::parse($booking->check_in_date)->startOfDay();

            // nếu chưa check_out_at (chưa trả phòng) thì tính tới hiện tại
            $end = $booking->check_out_at
                ? Carbon::parse($booking->check_out_at)->startOfMinute()
                : now()->startOfMinute();

            if ($end->lt($start)) {
                throw new \InvalidArgumentException('Thời gian trả phòng nhỏ hơn nhận phòng.');
            }

            $minutes = $start->diffInMinutes($end);
            $hours   = max(1, (int)ceil($minutes / 60));

            $roomTotal   = 0.0;
            $roomDetails = [];
            foreach ($booking->rooms as $room) {
                $rate  = (float)($room->roomType->hourly_rate ?? 0);
                $total = round($rate * $hours, 0);
                $roomTotal += $total;

                $roomDetails[] = [
                    'room_id'     => $room->id,
                    'room_number' => $room->room_number,
                    'unit'        => 'hour',
                    'unit_count'  => $hours,
                    'rate'        => $rate,
                    'total'       => $total,
                ];
            }
            $nights = 0;
        } else {
            // ----- Theo đêm -----
            $checkIn  = Carbon::parse($booking->check_in_date)->startOfDay();
            $checkOut = Carbon::parse($booking->check_out_date)->startOfDay();
            if ($checkOut->lt($checkIn)) {
                throw new \InvalidArgumentException('Ngày check-out không hợp lệ (trước ngày check-in)');
            }
            $nights = max(0, $checkIn->diffInDays($checkOut));

            $roomTotal   = 0.0;
            $roomDetails = [];
            foreach ($booking->rooms as $room) {
                $rate  = (float)($room->roomType->base_rate ?? 0);
                $total = round($rate * $nights, 0);
                $roomTotal += $total;

                $roomDetails[] = [
                    'room_id'     => $room->id,
                    'room_number' => $room->room_number,
                    'unit'        => 'night',
                    'unit_count'  => $nights,
                    'rate'        => $rate,
                    'total'       => $total,
                ];
            }
            $hours = 0;
        }

        // ----- Dịch vụ -----
        $serviceTotal = 0.0;
        foreach ($booking->services as $service) {
            $qty  = (int)($service->pivot->quantity ?? 1);
            $unit = isset($service->pivot->price) ? (float)$service->pivot->price : (float)($service->price ?? 0);
            $serviceTotal += round($unit * $qty, 0);
        }

        // ----- Tiện nghi phát sinh -----
        $amenityRows = BookingRoomAmenity::with(['room:id,room_number', 'amenity:id,name,price'])
            ->where('booking_id', $booking->id)
            ->get();

        $amenityTotal   = 0.0;
        $amenityDetails = [];
        foreach ($amenityRows as $row) {
            $qty  = (int)($row->quantity ?? 1);
            $unit = is_null($row->price) ? (float)optional($row->amenity)->price : (float)$row->price;
            $line = round($unit * $qty, 0);
            $amenityTotal += $line;

            $amenityDetails[] = [
                'room_id'      => $row->room_id,
                'room_number'  => optional($row->room)->room_number,
                'amenity_id'   => $row->amenity_id,
                'amenity_name' => optional($row->amenity)->name,
                'price'        => $unit,
                'quantity'     => $qty,
                'total'        => $line,
                'created_at'   => optional($row->created_at)?->toDateTimeString(),
            ];
        }

        // ----- Tổng hợp -----
        $discount      = (float)($booking->discount_amount ?? 0);
        $rawTotal      = round($roomTotal + $serviceTotal + $amenityTotal, 0);
        $subtotal      = max(0, $rawTotal - $discount);

        $depositAmount = (float)($booking->deposit_amount ?? 0);
        $isDepositPaid = (int)($booking->is_deposit_paid ?? 0);
        $finalTotal    = $isDepositPaid ? max(0, $subtotal - $depositAmount) : $subtotal;

        return [
            'is_hourly'        => $isHourly ? 1 : 0,
            'hours'            => $hours,
            'nights'           => $nights,

            'room_details'     => $roomDetails,
            'amenity_details'  => $amenityDetails,

            'room_total'       => $roomTotal,
            'service_total'    => $serviceTotal,
            'amenity_total'    => $amenityTotal,

            'discount'         => $discount,
            'raw_total'        => $rawTotal,
            'total_amount'     => $finalTotal,

            'deposit_amount'   => $depositAmount,
            'is_deposit_paid'  => $isDepositPaid,
        ];
    }


    public function create(Request $request)
    {
        $vnp_TmnCode    = Config::get('vnpay.tmn_code');
        $vnp_HashSecret = Config::get('vnpay.hash_secret');
        $vnp_Url        = Config::get('vnpay.url');
        $vnp_ReturnUrl  = Config::get('vnpay.return_url');

        $bookingId = $request->input('booking_id');
        $booking   = Booking::with(['rooms.roomType', 'services'])->find($bookingId);

        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }
        if ($booking->status === 'Checked-out') {
            return response()->json(['error' => 'Đơn này đã được thanh toán!'], 400);
        }

        try {
            $totals = $this->calculateBookingTotals($booking);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        $orderId = 'BOOKING-' . $bookingId . '-' . time();
        $amountVnp = (int) round($totals['total_amount'] * 100); // VND x100

        $data = [
            'vnp_Version'    => '2.1.0',
            'vnp_Command'    => 'pay',
            'vnp_TmnCode'    => $vnp_TmnCode,
            'vnp_Amount'     => $amountVnp,
            'vnp_CreateDate' => date('YmdHis'),
            'vnp_CurrCode'   => 'VND',
            'vnp_IpAddr'     => $request->ip(),
            'vnp_Locale'     => 'vn',
            'vnp_OrderInfo'  => 'Thanh toán đặt phòng #' . $bookingId,
            'vnp_OrderType'  => '250000',
            'vnp_ReturnUrl'  => $vnp_ReturnUrl,
            'vnp_TxnRef'     => $orderId,
        ];

        ksort($data);
        $query = http_build_query($data);
        $data['vnp_SecureHash'] = hash_hmac('sha512', $query, $vnp_HashSecret);

        $paymentUrl = $vnp_Url . '?' . http_build_query($data);

        // Lưu tổng tiền dự kiến (không đổi trạng thái)
        $booking->total_amount = $totals['total_amount'];
        $booking->save();

        return response()->json([
            'payment_url'  => $paymentUrl,
            'order_id'     => $orderId,
            'total_amount' => $totals['total_amount'],
            'is_hourly'    => $totals['is_hourly'],
            'hours'        => $totals['hours'],
            'nights'       => $totals['nights'],
            'breakdown'    => [
                'room_total'     => $totals['room_total'],
                'service_total'  => $totals['service_total'],
                'amenity_total'  => $totals['amenity_total'],
                'discount'       => $totals['discount'],
                'deposit_amount' => $totals['deposit_amount'],
            ],
        ]);
    }


    public function handleReturn(Request $request)
    {
        $vnp_HashSecret = Config::get('vnpay.hash_secret');

        // Lọc param bắt đầu bằng vnp_
        $inputData  = $request->all();
        $returnData = [];
        foreach ($inputData as $key => $value) {
            if (substr($key, 0, 4) === 'vnp_') {
                $returnData[$key] = $value;
            }
        }

        if (!isset($returnData['vnp_SecureHash'])) {
            return response()->json(['success' => false, 'message' => 'Thiếu vnp_SecureHash'], 400);
        }

        // Xác thực chữ ký
        $vnp_SecureHash = $returnData['vnp_SecureHash'];
        unset($returnData['vnp_SecureHash']);
        ksort($returnData);
        $query = http_build_query($returnData);
        $hash  = hash_hmac('sha512', $query, $vnp_HashSecret);

        if (!hash_equals($hash, $vnp_SecureHash)) {
            return response()->json(['success' => false, 'message' => 'Sai mã bảo mật'], 400);
        }

        // Lấy booking_id từ TxnRef
        preg_match('/BOOKING-(\d+)-/', (string)($request->input('vnp_TxnRef') ?? ''), $matches);
        $bookingId = $matches[1] ?? null;
        if (!$bookingId) {
            return response()->json(['error' => 'Transaction reference không hợp lệ'], 400);
        }

        $booking = Booking::with(['rooms.roomType', 'services'])->find($bookingId);
        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        if (($request->input('vnp_ResponseCode') ?? '') !== '00') {
            return response()->json(['success' => false, 'message' => 'Thanh toán thất bại'], 400);
        }

        // Số tiền VNPay đã thu (VND)
        $paidAmount = (int) round(((int)($request->input('vnp_Amount') ?? 0)) / 100);

        // Idempotency
        $existingInvoice = Invoice::where('booking_id', $booking->id)->latest('id')->first();
        if ($booking->status === 'Checked-out' && $existingInvoice) {
            $existingPayment = Payment::where('invoice_id', $existingInvoice->id)
                ->where('method', 'vnpay')
                ->where('status', 'success')
                ->first();
            if ($existingPayment) {
                return response()->json([
                    'success'          => true,
                    'message'          => 'Thanh toán đã được ghi nhận trước đó',
                    'booking_id'       => $booking->id,
                    'invoice_code'     => $existingInvoice->invoice_code,
                    'transaction_code' => $existingPayment->transaction_code,
                ]);
            }
        }

        DB::beginTransaction();
        try {
            // Tính lại để ghi nhận các khoản chi tiết (room/service/amenity)
            $totals = $this->calculateBookingTotals($booking);

            // Cập nhật booking
            $booking->update([
                'total_amount' => $totals['total_amount'],
                'status'       => 'Checked-out',
                'check_out_at' => now(),
            ]);

            foreach ($booking->rooms as $room) {
                $room->update(['status' => 'available']);
            }

            // Tạo/cập nhật hoá đơn (tổng tiền đặt theo số tiền thực trả)
            if (!$existingInvoice) {
                $today       = now()->format('Ymd');
                $countToday  = Invoice::whereDate('issued_date', today())->count() + 1;
                $invoiceCode = 'INV-' . $today . '-' . str_pad($countToday, 3, '0', STR_PAD_LEFT);

                $invoice = Invoice::create([
                    'invoice_code'    => $invoiceCode,
                    'booking_id'      => $booking->id,
                    'issued_date'     => now(),
                    'room_amount'     => $totals['room_total'],
                    'service_amount'  => $totals['service_total'],
                    'amenity_amount'  => $totals['amenity_total'],
                    'discount_amount' => $totals['discount'],
                    'deposit_amount'  => $totals['deposit_amount'],
                    'total_amount'    => $paidAmount, // khớp số tiền gateway đã thu
                ]);
            } else {
                $invoice = $existingInvoice;
                $invoice->update([
                    'issued_date'     => now(),
                    'room_amount'     => $totals['room_total'],
                    'service_amount'  => $totals['service_total'],
                    'amenity_amount'  => $totals['amenity_total'],
                    'discount_amount' => $totals['discount'],
                    'deposit_amount'  => $totals['deposit_amount'],
                    'total_amount'    => $paidAmount,
                ]);
            }

            // Ghi nhận payment
            $transactionCode = $request->input('vnp_TransactionNo') ?? null;

            $existPay = Payment::where('invoice_id', $invoice->id)
                ->when($transactionCode, fn($q) => $q->where('transaction_code', $transactionCode))
                ->where('method', 'vnpay')
                ->first();

            if (!$existPay) {
                Payment::create([
                    'invoice_id'       => $invoice->id,
                    'amount'           => $paidAmount,
                    'method'           => 'vnpay',
                    'transaction_code' => $transactionCode,
                    'paid_at'          => now(),
                    'status'           => 'success',
                ]);
            }

            DB::commit();

            return response()->json([
                'success'          => true,
                'message'          => 'Thanh toán thành công',
                'booking_id'       => $booking->id,
                'invoice_code'     => $invoice->invoice_code,
                'transaction_code' => $transactionCode,
                'is_hourly'        => $totals['is_hourly'],
                'hours'            => $totals['hours'],
                'nights'           => $totals['nights'],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi xử lý thanh toán: ' . $e->getMessage()], 500);
        }
    }


    public function payDepositOnline(Request $request)
    {
        $bookingId = $request->input('booking_id');
        $booking = Booking::find($bookingId);

        if (!$booking) {
            return response()->json(['error' => 'Không tìm thấy booking'], 404);
        }

        if ($booking->is_deposit_paid) {
            return response()->json(['error' => 'Đơn đặt phòng đã được thanh toán đặt cọc'], 422);
        }

        $vnp_TmnCode    = config('vnpay.tmn_code');
        $vnp_HashSecret = config('vnpay.hash_secret');
        $vnp_Url        = config('vnpay.url');
        $vnp_ReturnUrl  = config('vnpay.return_url');

        $depositAmount = $booking->deposit_amount;
        $orderId = 'BOOKING-DEPOSIT-' . $bookingId . '-' . time();

        $data = [
            'vnp_Version'    => '2.1.0',
            'vnp_Command'    => 'pay',
            'vnp_TmnCode'    => $vnp_TmnCode,
            'vnp_Amount'     => $depositAmount * 100, // nhân 100
            'vnp_CurrCode'   => 'VND',
            'vnp_TxnRef'     => $orderId,
            'vnp_OrderInfo'  => 'Thanh toán đặt cọc cho booking #' . $bookingId,
            'vnp_OrderType'  => 'other',
            'vnp_Locale'     => 'vn',
            'vnp_ReturnUrl'  => $vnp_ReturnUrl,
            'vnp_IpAddr'     => $request->ip(),
            'vnp_CreateDate' => now()->format('YmdHis'),
        ];

        // Bắt buộc sắp xếp theo thứ tự tăng dần key trước khi tạo hash
        ksort($data);
        $hashData = '';
        foreach ($data as $key => $value) {
            $hashData .= urlencode($key) . '=' . urlencode($value) . '&';
        }
        $hashData = rtrim($hashData, '&');
        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
        $data['vnp_SecureHash'] = $secureHash;

        $paymentUrl = $vnp_Url . '?' . http_build_query($data);


        // Trả về JSON cho frontend gọi hoặc redirect nếu là từ trình duyệt
        if ($request->expectsJson()) {
            return response()->json([
                'payment_url'    => $paymentUrl,
                'order_id'       => $orderId,
                'deposit_amount' => $depositAmount,
            ]);
        } else {
            return redirect()->away($paymentUrl);
        }
    }



    public function handleDepositReturn(Request $request)
    {
        $vnp_HashSecret = Config::get('vnpay.hash_secret');
        $inputData = $request->all();
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? null;

        if (!$vnp_SecureHash) {
            return response()->json(['success' => false, 'message' => 'Thiếu mã bảo mật'], 400);
        }

        // Lấy các vnp_ tham số để xác minh chữ ký
        $vnpData = [];
        foreach ($inputData as $key => $value) {
            if (str_starts_with($key, 'vnp_') && $key !== 'vnp_SecureHash') {
                $vnpData[$key] = $value;
            }
        }

        ksort($vnpData);
        $hashData = '';
        foreach ($vnpData as $key => $value) {
            $hashData .= urlencode($key) . '=' . urlencode($value) . '&';
        }
        $hashData = rtrim($hashData, '&');

        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
        if ($secureHash !== $vnp_SecureHash) {
            return response()->json(['success' => false, 'message' => 'Sai mã bảo mật'], 400);
        }

        if ($inputData['vnp_ResponseCode'] !== '00') {
            return response()->json(['success' => false, 'message' => 'Thanh toán thất bại'], 400);
        }

        // Trích booking_id từ TxnRef
        if (!preg_match('/BOOKING-DEPOSIT-(\d+)-/', $inputData['vnp_TxnRef'], $matches)) {
            return response()->json(['error' => 'Mã giao dịch không hợp lệ'], 400);
        }

        $bookingId = $matches[1];
        $booking = Booking::find($bookingId);
        if (!$booking) {
            return response()->json(['error' => 'Không tìm thấy booking'], 404);
        }

        // Đã thanh toán rồi thì không xử lý lại
        if ($booking->is_deposit_paid) {
            return response()->json(['message' => 'Đặt cọc đã được thanh toán trước đó'], 422);
        }

        $depositAmount = $inputData['vnp_Amount'] / 100;

        DB::beginTransaction();
        try {
            // Ghi log thanh toán (KHÔNG có invoice_id)
            Payment::create([
                'invoice_id'       => null, // vì chưa có hóa đơn
                'amount'           => $depositAmount,
                'method'           => 'vnpay',
                'transaction_code' => $inputData['vnp_TransactionNo'] ?? null,
                'paid_at'          => now(),
                'status'           => 'success',
            ]);

            // Cập nhật đơn đặt phòng
            $booking->update([
                'deposit_amount'   => $depositAmount,
                'is_deposit_paid'  => true,
                'status'           => 'Confirmed',
            ]);

            DB::commit();

            return response()->json([
                'success'          => true,
                'message'          => 'Thanh toán đặt cọc thành công',
                'booking_id'       => $booking->id,
                'transaction_code' => $inputData['vnp_TransactionNo'] ?? null,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Lỗi xử lý: ' . $e->getMessage()], 500);
        }
    }
}
