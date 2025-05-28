<?php
// app/Http/Controllers/Api/BookingPromotionController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Booking;
use App\Models\Promotion;
use Carbon\Carbon;

class BookingPromotionController extends Controller
{
    /**
     * Áp dụng mã khuyến mãi cho một Booking
     */
    public function apply(Request $request, Booking $booking): JsonResponse
    {
        try {
            // 1. Validate input
            $request->validate([
                'promotion_code' => 'required|string|exists:promotions,code',
            ]);

            // 2. Chạy trong transaction để đảm bảo atomic & lock row
            $response = DB::transaction(function () use ($request, $booking) {
                // Khóa bản ghi promotion
                $promo = Promotion::where('code', $request->promotion_code)
                    ->lockForUpdate()
                    ->first();

                // Kiểm tra hiệu lực
                if (! $promo->isValid()) {
                    return response()->json([
                        'message' => 'Khuyến mãi hết hạn hoặc đã vượt số lần sử dụng.'
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                // Tăng used_count
                $promo->increment('used_count');

                // Tính discount
                $original = $booking->total_amount;
                $discount = $promo->discount_type === 'percent'
                    ? $original * ($promo->discount_value / 100)
                    : $promo->discount_value;

                // Cập nhật booking
                $booking->forceFill([
                    'discount_amount' => $discount,
                    'total_amount'    => max(0, $original - $discount),
                ])->save();

                // Ghi vào pivot table
                $booking->promotions()->attach($promo->id, [
                    'promotion_code' => $promo->code,
                    'applied_at'     => Carbon::now(),
                ]);

                return response()->json([
                    'message'     => 'Áp dụng khuyến mãi thành công.',
                    'discount'    => $discount,
                    'total_after' => $booking->total_amount,
                    'used_count'  => $promo->used_count,
                ], Response::HTTP_OK);
            });

            return $response;
        } catch (\Throwable $e) {
            // Log chi tiết để debug
            Log::error('Apply Promotion error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Đã có lỗi xảy ra khi áp dụng khuyến mãi.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
