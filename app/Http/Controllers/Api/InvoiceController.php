<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    // public function generate($booking_id)
    // {
    //     $booking = DB::table('bookings')->where('id', $booking_id)->first();

    //     if (!$booking) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Không tìm thấy đơn đặt phòng',
    //         ], 404);
    //     }

    //     // Tính toán
    //     $subTotal = (float) $booking->raw_total - (float) $booking->discount_amount;
    //     $vatRate = 0.1;
    //     $vatAmount = $subTotal * $vatRate;
    //     $total = $subTotal + $vatAmount;
    //     $remaining = $total - (float) $booking->deposit_amount;

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Hóa đơn được tạo thành công',
    //         'invoice' => [
    //             'booking_id' => $booking->id,
    //             'customer_id' => $booking->customer_id,
    //             'room_id' => $booking->room_id,
    //             'check_in_date' => $booking->check_in_date,
    //             'check_out_date' => $booking->check_out_date,
    //             'status' => $booking->status,

    //             'raw_total' => number_format($booking->raw_total, 2),
    //             'discount_amount' => number_format($booking->discount_amount, 2),
    //             'vat' => number_format($vatAmount, 2),
    //             'total' => number_format($total, 2),
    //             'deposit' => number_format($booking->deposit_amount, 2),
    //             'remaining_amount' => number_format($remaining, 2),
    //         ]
    //     ]);
    // }

    // public function generateGroupInvoice($customer_id)
    // {
    //     $bookings = DB::table('bookings')
    //         ->where('customer_id', $customer_id)
    //         ->where('status', 'Checked-out')
    //         ->get();


    //     if ($bookings->isEmpty()) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Khách chưa trả phòng. Không thể in hóa đơn.',
    //         ], 400);
    //     }
    //     // Lấy danh sách booking_id
    //     $bookingIds = $bookings->pluck('id')->toArray();

    //     // Tính tổng tiền dịch vụ (join bảng trung gian)
    //     $totalService = DB::table('booking_service')
    //         ->join('services', 'booking_service.service_id', '=', 'services.id')
    //         ->whereIn('booking_service.booking_id', $bookingIds)
    //         ->select(DB::raw('SUM(booking_service.quantity * services.price) as total'))
    //         ->value('total');

    //     $totalRaw = $bookings->sum('raw_total');
    //     $totalDiscount = $bookings->sum('discount_amount');
    //     $totalDeposit = $bookings->sum('deposit_amount');

    //     $subTotal = $totalRaw - $totalDiscount + $totalService;
    //     $vat = $subTotal * 0.08; // 8%
    //     $totalAmount = $subTotal + $vat;
    //     $amountDue = $totalAmount - $totalDeposit;

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Hóa đơn tổng hợp nhiều phòng',
    //         'customer_id' => $customer_id,
    //         'invoice_summary' => [
    //             'tong_gia_phong' => number_format($totalRaw, 2),
    //             'tong_dich_vu' => number_format($totalService, 2),
    //             'giam_gia' => number_format($totalDiscount, 2),
    //             'vat' => number_format($vat, 2),
    //             'tong_thanh_toan' => number_format($totalAmount, 2),
    //             'tong_tien_dat_coc' => number_format($totalDeposit, 2),
    //             'so_tien_phai_tra' => number_format($amountDue, 2),
    //         ],
    //         'chi_tiet_phong' => $bookings->map(function ($b) {
    //             return [
    //                 'booking_id' => $b->id,
    //                 'room_id' => $b->room_id,
    //                 'check_in' => $b->check_in_date,
    //                 'check_out' => $b->check_out_date,
    //                 'raw_total' => $b->raw_total,
    //                 'discount' => $b->discount_amount,
    //                 'deposit' => $b->deposit_amount,
    //             ];
    //         }),
    //     ]);
    // }
}
