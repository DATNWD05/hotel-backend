<?php
// app/Http/Controllers/Api/PromotionController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotions\StorePromotionRequest;
use App\Http\Requests\Promotions\UpdatePromotionRequest;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PromotionController extends Controller
{
    public function index(): JsonResponse
    {
        // Lấy các promotion đang active
        $promotions = Promotion::active()->get();
        return response()->json($promotions);
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        try {
            $promo = Promotion::create($request->validated());
            return response()->json($promo, Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            Log::error('Create Promotion failed: '.$e->getMessage());
            return response()->json([
                'error' => 'Tạo mã khuyến mãi thất bại.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Promotion $promotion): JsonResponse
    {
        return response()->json($promotion);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        try {
            $promotion->update($request->validated());
            return response()->json($promotion);
        } catch (\Throwable $e) {
            Log::error('Update Promotion failed: '.$e->getMessage());
            return response()->json([
                'error' => 'Cập nhật mã khuyến mãi thất bại.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        try {
            if ($promotion->used_count > 0) {
                // Chỉ ẩn nếu đã dùng
                $promotion->update(['is_active' => false]);
                return response()->json([
                    'message' => 'Mã đã được ẩn.'
                ], Response::HTTP_OK);
            }

            // Xóa hoàn toàn nếu chưa dùng
            $promotion->delete();
            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Throwable $e) {
            Log::error('Delete Promotion failed: '.$e->getMessage());
            return response()->json([
                'error' => 'Xóa mã khuyến mãi thất bại.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
