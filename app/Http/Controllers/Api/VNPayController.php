<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Models\Booking; // Thêm model Booking
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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

        // Tính tiền phòng
        $roomTotal = 0;
        $roomDetails = [];

        foreach ($booking->rooms as $room) {
            $rate = floatval(optional($room->roomType)->base_rate ?? 0);
            $total = $rate * $nights;

            $roomTotal += $total;

            $roomDetails[] = [
                'room_number' => $room->room_number,
                'base_rate' => $rate,
                'total' => $total
            ];
        }

        // Tính tiền dịch vụ
        $serviceTotal = 0;
        foreach ($booking->services as $service) {
            $quantity = intval($service->pivot->quantity ?? 1);
            $serviceTotal += floatval($service->price) * $quantity;
        }

        // giảm giá
        $discountAmount = floatval($booking->discount_amount ?? 0);
        $discountType = $booking->discount_type ?? 'percent';
        $rawTotal = $roomTotal + $serviceTotal;

        if ($discountType === 'percent') {
            $discount = ($discountAmount > 0) ? ($rawTotal * $discountAmount / 100) : 0;
        } elseif ($discountType === 'amount') {
            $discount = min($discountAmount, $rawTotal); // không cho trừ quá
        } else {
            $discount = 0;
        }

        $totalAmount = $rawTotal - $discount;

        return $totalAmount;
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
            $totalAmount = $this->calculateBookingTotals($booking);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        $orderId = 'BOOKING-' . $bookingId . '-' . time();

        $data = [
            'vnp_Version'    => '2.1.0',
            'vnp_Command'    => 'pay',
            'vnp_TmnCode'    => $vnp_TmnCode,
            'vnp_Amount'     => $totalAmount * 100, // VNPAY dùng đơn vị là đồng * 100
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

        $booking->total_amount = $totalAmount;
        $booking->save();

        return response()->json([
            'payment_url'   => $paymentUrl,
            'order_id'      => $orderId,
            'total_amount'  => $totalAmount
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
            return response()->json([
                'success' => false,
                'message' => 'Thiếu vnp_SecureHash',
                'data' => $inputData
            ], 400);
        }

        $vnp_SecureHash = $returnData['vnp_SecureHash'];
        unset($returnData['vnp_SecureHash']);

        ksort($returnData);
        $query = http_build_query($returnData);
        $hash = hash_hmac('sha512', $query, $vnp_HashSecret);

        if ($hash === $vnp_SecureHash) {
            preg_match('/BOOKING-(\d+)-/', $returnData['vnp_TxnRef'], $matches);
            $bookingId = $matches[1] ?? null;

            if ($bookingId) {
                $booking = Booking::with('rooms')->find($bookingId);

                if (!$booking) {
                    return response()->json(['error' => 'Booking not found'], 404);
                }

                if ($returnData['vnp_ResponseCode'] == '00') {
                    $totalAmount = $this->calculateBookingTotals($booking);

                    $booking->update([
                        'total_amount'  => $totalAmount,
                        'status'        => 'Checked-out',
                        'check_out_at'  => now(),
                    ]);

                    foreach ($booking->rooms as $room) {
                        $room->update(['status' => 'available']);
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Thanh toán thành công',
                        'data' => $returnData
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Thanh toán thất bại',
                        'data' => $returnData
                    ]);
                }
            } else {
                return response()->json(['error' => 'Transaction reference không hợp lệ'], 400);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Sai mã bảo mật (secure hash)'
            ]);
        }
    }
}
