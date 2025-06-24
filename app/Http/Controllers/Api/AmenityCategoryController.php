<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AmenityCategory\StoreAmenityCategoryRequest;
use App\Http\Requests\AmenityCategory\UpdateAmenityCategoryRequest;
use App\Http\Resources\AmenityCategoryResource;
use App\Models\AmenityCategory;
use Illuminate\Http\JsonResponse;

class AmenityCategoryController extends Controller
{
    // GET /api/amenity-categories
    public function index(): JsonResponse
    {
        $paginated = AmenityCategory::with('amenities')
            ->orderBy('created_at', 'desc')
            ->paginate(10);


        return response()->json([
            'status' => 'success',
            'data'   => AmenityCategoryResource::collection($paginated->items()),
            'meta'   => [
                'current_page' => $paginated->currentPage(),
                'per_page'     => $paginated->perPage(),
                'last_page'    => $paginated->lastPage(),
                'total'        => $paginated->total(),
            ],
        ], 200);
    }

    // POST /api/amenity-categories
    public function store(StoreAmenityCategoryRequest $request): JsonResponse
    {
        $category = AmenityCategory::create($request->validated());

        return response()->json([
            'status' => 'success',
            'data'   => new AmenityCategoryResource($category),
        ], 201);
    }


    // GET /api/amenity-categories/{amenity_category}
    public function show(AmenityCategory $amenity_category): JsonResponse
    {
        $amenity_category->load('amenities');

        return response()->json([
            'status' => 'success',
            'data'   => new AmenityCategoryResource($amenity_category),
        ], 200);
    }

    // PUT /api/amenity-categories/{amenity_category}
    public function update(UpdateAmenityCategoryRequest $request, AmenityCategory $amenity_category): JsonResponse
    {
        $amenity_category->update($request->validated());

        return response()->json([
            'status' => 'success',
            'data'   => new AmenityCategoryResource($amenity_category),
        ], 200);
    }


    // DELETE /api/amenity-categories/{amenity_category}
    public function destroy(AmenityCategory $amenity_category): JsonResponse
    {
        try {
            if ($amenity_category->amenities()->exists()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Không thể xóa: vẫn còn tiện ích thuộc category này.'
                ], 409);
            }
            $amenity_category->delete();
            return response()->json(null, 204);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Xóa thất bại: ' . $e->getMessage()
            ], 500);
        }
    }

    public function trashed(): JsonResponse
    {
        $categories = AmenityCategory::onlyTrashed()
            ->orderBy('deleted_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => AmenityCategoryResource::collection($categories),
        ], 200);
    }

    public function restore(int $id): JsonResponse
    {
        $category = AmenityCategory::onlyTrashed()->findOrFail($id);

        $category->restore();

        return response()->json([
            'status' => 'success',
            'data'   => new AmenityCategoryResource($category),
        ], 200);
    }

    public function forceDelete(int $id): JsonResponse
    {
        $category = AmenityCategory::onlyTrashed()->findOrFail($id);

        $category->forceDelete();

        return response()->json(null, 204);
    }
}
