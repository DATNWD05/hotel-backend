<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Amentity\StoreAmenityRequest;
use App\Http\Requests\Amentity\UpdateAmenityRequest;
use App\Http\Resources\AmenityResource;
use App\Models\Amenity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class AmenityController extends Controller
{
    /**
     * GET /api/amenities
     */
   public function index(): JsonResponse
{
    $paginated = Amenity::with('category')
        ->orderBy('created_at', 'desc')
        ->paginate(10);

    return response()->json([
        'status' => 'success',
        'data'   => AmenityResource::collection($paginated->items()),
        'meta'   => [
            'current_page' => $paginated->currentPage(),
            'per_page'     => $paginated->perPage(),
            'last_page'    => $paginated->lastPage(),
            'total'        => $paginated->total(),
        ],
    ], 200);
}


    /**
     * POST /api/amenities
     */
    public function store(StoreAmenityRequest $request): JsonResponse
    {
        $amenity = Amenity::create($request->validated());

        return response()->json([
            'status' => 'success',
            'data'   => new AmenityResource($amenity),
        ], 201);
    }

    /**
     * GET /api/amenities/{amenity}
     */
    public function show(Amenity $amenity): JsonResponse
    {
        $amenity->load('category');

        return response()->json([
            'status' => 'success',
            'data'   => new AmenityResource($amenity),
        ], 200);
    }

    /**
     * PUT /api/amenities/{amenity}
     */
    public function update(UpdateAmenityRequest $request, Amenity $amenity): JsonResponse
    {
        $amenity->update($request->validated());

        return response()->json([
            'status' => 'success',
            'data'   => new AmenityResource($amenity),
        ], 200);
    }

    /**
     * DELETE /api/amenities/{amenity} (soft delete)
     */
    public function destroy(Amenity $amenity): JsonResponse
    {
        try {
            $amenity->delete(); // dùng SoftDeletes
            return response()->json(null, 204);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Xóa thất bại: ' . $e->getMessage(),
            ], 500);
        }
    }
}
