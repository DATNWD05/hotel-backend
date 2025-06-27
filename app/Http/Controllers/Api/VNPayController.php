<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use App\Models\Booking; // Thêm model Booking
use Illuminate\Support\Facades\Log;

class VNPayController extends Controller
{
    public function create(Request $request)
    {
        $vnp_TmnCode = Config::get('vnpay.tmn_code');
        $vnp_HashSecret = Config::get('vnpay.hash_secret');
        $vnp_Url = Config::get('vnpay.url');
        $vnp_ReturnUrl = Config::get('vnpay.return_url');

        // Lấy id từ request (có thể từ body hoặc query parameter)
        $bookingId = $request->input('booking_id'); // Giả sử frontend gửi booking_id

        // Kiểm tra và lấy total_amount từ bảng Bookings
        $booking = Booking::find($bookingId);

        if (!$booking) {
            return response()->json(['error' => 'Booking not found'], 404);
        }

        $totalAmount = $booking->total_amount * 100; // Nhân 100 vì VNPay yêu cầu đơn vị nhỏ nhất (VND)

        $orderInfo = "Đã bao gồm cả tiền phòng, dịch vụ, tiện nghi sử dụng";
        $orderType = '250000';
        $orderId = 'BOOKING-' . $bookingId . '-' . time();

        // Tạo tham số gửi sang VNPay
        $data = [
            'vnp_Version' => '2.1.0',
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $vnp_TmnCode,
            'vnp_Amount' => $totalAmount,
            'vnp_CreateDate' => date('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => request()->ip(),
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => $orderInfo,
            'vnp_OrderType' => $orderType,
            'vnp_ReturnUrl' => $vnp_ReturnUrl,
            'vnp_TxnRef' => $orderId,
        ];

        // Debug để kiểm tra
        Log::info('VNPay Data', ['data' => $data]);

        // Sắp xếp các tham số theo thứ tự bảng chữ cái
        ksort($data);

        // Tạo chuỗi query
        $query = http_build_query($data);

        // Tạo hash để kiểm tra tính toàn vẹn
        $secureHash = hash_hmac('sha512', $query, $vnp_HashSecret);
        $data['vnp_SecureHash'] = $secureHash;

        // Tạo URL thanh toán hoàn chỉnh
        $paymentUrl = $vnp_Url . '?' . http_build_query($data);

        // Trả về URL cho frontend
        return response()->json([
            'payment_url' => $paymentUrl,
            'order_id' => $orderId,
            'total_amount' => $totalAmount / 100
        ]);
    }

    public function handleReturn(Request $request)
    {
        $vnp_HashSecret = Config::get('vnpay.hash_secret');
        $inputData = $request->all();
        $returnData = [];

        // Lọc các tham số bắt đầu bằng vnp_
        foreach ($inputData as $key => $value) {
            if (substr($key, 0, 4) === 'vnp_') {
                $returnData[$key] = $value;
            }
        }

        // Kiểm tra nếu vnp_SecureHash không tồn tại
        if (!isset($returnData['vnp_SecureHash'])) {
            return response()->json([
                'success' => false,
                'message' => 'Missing vnp_SecureHash parameter',
                'data' => $inputData
            ], 400);
        }

        $vnp_SecureHash = $returnData['vnp_SecureHash'];
        unset($returnData['vnp_SecureHash']);

        ksort($returnData);
        $query = http_build_query($returnData);
        $hash = hash_hmac('sha512', $query, $vnp_HashSecret);

        if ($hash === $vnp_SecureHash) {
            // Lấy booking_id từ vnp_TxnRef
            $txnRef = $returnData['vnp_TxnRef'];
            preg_match('/BOOKING-(\d+)-/', $txnRef, $matches);
            $bookingId = $matches[1] ?? null;

            if ($bookingId) {
                $booking = Booking::find($bookingId);

                if ($booking) {
                    if ($returnData['vnp_ResponseCode'] == '00') {
                        // Thanh toán thành công
                        $booking->update([
                            'status' => 'Checked-out',
                            'check_out_at' => now()
                        ]);

                        // Cập nhật trạng thái phòng về 'available'
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
                    return response()->json(['error' => 'Booking not found'], 404);
                }
            } else {
                return response()->json(['error' => 'Invalid transaction reference'], 400);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ'
            ]);
        }
    }
}
