<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class VNPayController extends Controller
{
    private function calculateBookingTotals($booking)
    {
        $checkIn = Carbon::parse($booking->check_in_date);
        $checkOut = Carbon::parse($booking->check_out_date);

        if ($checkOut->lt($checkIn)) {
            throw new \Exception('Ngày check-out không hợp lệ (trước ngày check-in)');
        }

        $nights = $checkIn->diffInDays($checkOut);
        $roomTotal = 0;
        foreach ($booking->rooms as $room) {
            $rate = floatval(optional($room->roomType)->base_rate ?? 0);
            $roomTotal += $rate * $nights;
        }

        $serviceTotal = 0;
        foreach ($booking->services as $service) {
            $quantity = intval($service->pivot->quantity ?? 1);
            $serviceTotal += floatval($service->price) * $quantity;
        }

        $discountAmount = floatval($booking->discount_amount ?? 0);
        $discountType = $booking->discount_type ?? 'percent';
        $rawTotal = $roomTotal + $serviceTotal;

        if ($discountType === 'percent') {
            $discount = $discountAmount > 0 ? ($rawTotal * $discountAmount / 100) : 0;
        } elseif ($discountType === 'amount') {
            $discount = min($discountAmount, $rawTotal);
        } else {
            $discount = 0;
        }

        $totalAmount = $rawTotal - $discount;

        return [
            'room_total' => $roomTotal,
            'service_total' => $serviceTotal,
            'discount' => $discount,
            'total_amount' => $totalAmount,
            'raw_total' => $rawTotal
        ];
    }

    public function create(Request $request)
    {
        $vnp_TmnCode    = Config::get('vnpay.tmn_code');
        $vnp_HashSecret = Config::get('vnpay.hash_secret');
        $vnp_Url        = Config::get('vnpay.url');
        $vnp_ReturnUrl  = Config::get('vnpay.return_url');

        $bookingId = $request->input('booking_id');
        $booking = Booking::with(['rooms.roomType', 'services'])->find($bookingId);

        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        try {
            $totals = $this->calculateBookingTotals($booking);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        $orderId = 'BOOKING-' . $bookingId . '-' . time();

        $data = [
            'vnp_Version'    => '2.1.0',
            'vnp_Command'    => 'pay',
            'vnp_TmnCode'    => $vnp_TmnCode,
            'vnp_Amount'     => $totals['total_amount'] * 100,
            'vnp_CreateDate' => date('YmdHis'),
            'vnp_CurrCode'   => 'VND',
            'vnp_IpAddr'     => request()->ip(),
            'vnp_Locale'     => 'vn',
            'vnp_OrderInfo'  => 'Thanh toán đặt phòng #' . $bookingId,
            'vnp_OrderType'  => '250000',
            'vnp_ReturnUrl'  => $vnp_ReturnUrl,
            'vnp_TxnRef'     => $orderId,
        ];

        ksort($data);
        $query = http_build_query($data);
        $secureHash = hash_hmac('sha512', $query, $vnp_HashSecret);
        $data['vnp_SecureHash'] = $secureHash;

        $paymentUrl = $vnp_Url . '?' . http_build_query($data);

        $booking->total_amount = $totals['total_amount'];
        $booking->save();

        return response()->json([
            'payment_url'   => $paymentUrl,
            'order_id'      => $orderId,
            'total_amount'  => $totals['total_amount']
        ]);
    }

    public function handleReturn(Request $request)
    {
        $vnp_HashSecret = Config::get('vnpay.hash_secret');
        $inputData = $request->all();
        $returnData = [];

        foreach ($inputData as $key => $value) {
            if (substr($key, 0, 4) === 'vnp_') {
                $returnData[$key] = $value;
            }
        }

        if (!isset($returnData['vnp_SecureHash'])) {
            return response()->json(['success' => false, 'message' => 'Thiếu vnp_SecureHash'], 400);
        }

        $vnp_SecureHash = $returnData['vnp_SecureHash'];
        unset($returnData['vnp_SecureHash']);
        ksort($returnData);
        $query = http_build_query($returnData);
        $hash = hash_hmac('sha512', $query, $vnp_HashSecret);

        if ($hash !== $vnp_SecureHash) {
            return response()->json(['success' => false, 'message' => 'Sai mã bảo mật'], 400);
        }

        preg_match('/BOOKING-(\d+)-/', $returnData['vnp_TxnRef'], $matches);
        $bookingId = $matches[1] ?? null;

        if (!$bookingId) {
            return response()->json(['error' => 'Transaction reference không hợp lệ'], 400);
        }

        $booking = Booking::with('rooms.roomType', 'services')->find($bookingId);
        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        if ($returnData['vnp_ResponseCode'] != '00') {
            return response()->json(['success' => false, 'message' => 'Thanh toán thất bại'], 400);
        }

        DB::beginTransaction();
        try {
            $totals = $this->calculateBookingTotals($booking);

            $booking->update([
                'total_amount' => $totals['total_amount'],
                'status' => 'Checked-out',
                'check_out_at' => now(),
            ]);

            foreach ($booking->rooms as $room) {
                $room->update(['status' => 'available']);
            }

            // Tạo invoice
            $today = now()->format('Ymd');
            $countToday = Invoice::whereDate('issued_date', today())->count() + 1;
            $invoiceCode = 'INV-' . $today . '-' . str_pad($countToday, 3, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'invoice_code' => $invoiceCode,
                'booking_id' => $booking->id,
                'issued_date' => now(),
                'room_amount' => $totals['room_total'],
                'service_amount' => $totals['service_total'],
                'discount_amount' => $totals['discount'],
                'deposit_amount' => $booking->deposit_amount ?? 0,
                'total_amount' => $totals['total_amount'],
            ]);


            Log::info('VNPay Return Data:', $returnData);

            // Tạo payment
            Payment::create([
                'invoice_id' => $invoice->id,
                'amount' => $totals['total_amount'],
                'method' => 'vnpay',
                'transaction_code' => $returnData['vnp_TransactionNo'] ?? null,
                'paid_at' => now(),
                'status' => 'success',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Thanh toán thành công',
                'booking_id' => $booking->id,
                'invoice_code' => $invoice->invoice_code,
                'transaction_code' => $returnData['vnp_TransactionNo'] ?? null
            ]);
        } catch (\Exception $e) {
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

        // Tính tiền đặt cọc: 30% tổng
        $depositAmount = $booking->deposit_amount;


        // Cấu hình VNPay
        $vnp_TmnCode    = Config::get('vnpay.tmn_code');
        $vnp_HashSecret = Config::get('vnpay.hash_secret');
        $vnp_Url        = Config::get('vnpay.url');
        $vnp_ReturnUrl  = route('vnpay.deposit.return'); // Đảm bảo bạn có route tên này

        $orderId = 'BOOKING-DEPOSIT-' . $bookingId . '-' . time();

        $data = [
            'vnp_Version'    => '2.1.0',
            'vnp_Command'    => 'pay',
            'vnp_TmnCode'    => $vnp_TmnCode,
            'vnp_Amount'     => $depositAmount * 100,
            'vnp_CreateDate' => date('YmdHis'),
            'vnp_CurrCode'   => 'VND',
            'vnp_IpAddr'     => request()->ip(),
            'vnp_Locale'     => 'vn',
            'vnp_OrderInfo'  => 'Thanh toán đặt cọc cho booking #' . $bookingId,
            'vnp_OrderType'  => 'other',
            'vnp_ReturnUrl'  => $vnp_ReturnUrl,
            'vnp_TxnRef'     => $orderId,
        ];

        ksort($data);
        $hashData = '';
        foreach ($data as $key => $value) {
            $hashData .= urlencode($key) . '=' . urlencode($value) . '&';
        }
        $hashData = rtrim($hashData, '&');
        $secureHash = hash_hmac('sha512', $hashData, $vnp_HashSecret);
        $data['vnp_SecureHash'] = $secureHash;

        $paymentUrl = $vnp_Url . '?' . http_build_query($data);


        // Trả về URL thanh toán
        return response()->json([
            'payment_url'    => $vnp_Url . '?' . http_build_query($data),
            'order_id'       => $orderId,
            'deposit_amount' => $booking->deposit_amount,
        ]);
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
