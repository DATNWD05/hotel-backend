<?php

namespace App\Http\Controllers\Api;

use App\Models\Promotion;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\Promotions\StorePromotionRequest;
use App\Http\Requests\Promotions\UpdatePromotionRequest;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PromotionController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Promotion::class, 'promotions');
    }
    public function index(): JsonResponse
    {
        $query = Promotion::paginate(10);

        return response()->json([
            'status' => 'success',
            'data'  => $query->items(),
            'meta' => [
                'current_page' => $query->currentPage(),
                'last_page'   => $query->lastPage(),
                'per_page'    => $query->perPage(),
                'total'       => $query->total(),
            ],
        ]);
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        try {
            $data  = $request->validated();
            $promo = Promotion::create($data);
            // Đồng bộ trạng thái ngay
            $promo->syncStatus();

            return response()->json($promo, Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            Log::error("Tạo khuyến mãi lỗi: {$e->getMessage()}");
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
            $promotion->syncStatus();
            return response()->json($promotion);
        } catch (\Throwable $e) {
            Log::error("Cập nhật khuyến mãi lỗi: {$e->getMessage()}");
            return response()->json([
                'error' => 'Cập nhật mã khuyến mãi thất bại.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        try {
            if ($promotion->used_count > 0) {
                $promotion->update([
                    'status'    => 'cancelled',
                    'is_active' => false
                ]);
                return response()->json([
                    'message' => 'Khuyến mãi đã bị ẩn (cancelled).'
                ], Response::HTTP_OK);
            }
            $promotion->delete();
            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Throwable $e) {
            Log::error("Xóa khuyến mãi lỗi: {$e->getMessage()}");
            return response()->json([
                'error' => 'Xóa mã khuyến mãi thất bại.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
