<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\ServiceCategory;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ServiceCategoryController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(ServiceCategory::class, 'service_category');
    }

    // Lấy tất cả danh mục dịch vụ
    public function index()
    {
        $data = ServiceCategory::paginate(10);

        return response()->json([
            'status' => 'success',
            'data'   => $data->items(),
            'meta'   => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'total'        => $data->total(),
            ],
        ]);
    }


    // Lấy danh mục dịch vụ theo ID
    public function show(ServiceCategory $serviceCategory)
    {
        return response()->json([
            'message' => 'Thông tin danh mục dịch vụ',
            'data' => $serviceCategory
        ], 200);
    }

    // Tạo mới danh mục dịch vụ
    public function store(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ], [
            'name.required' => 'Tên danh mục là bắt buộc.',
            'name.string' => 'Tên danh mục phải là chuỗi ký tự.',
            'name.max' => 'Tên danh mục không được quá 255 ký tự.',
            'description.string' => 'Mô tả phải là chuỗi ký tự.',
            'description.max' => 'Mô tả không được quá 500 ký tự.',
        ]);

        // Tạo danh mục mới
        $category = ServiceCategory::create($request->all());

        // Trả về thông báo thành công
        return response()->json([
            'message' => 'Danh mục dịch vụ đã được tạo thành công.',
            'data' => $category
        ], 201); // Trả về danh mục dịch vụ vừa tạo
    }

    // Cập nhật danh mục dịch vụ
    public function update(Request $request, ServiceCategory $serviceCategory)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ], [
            'name.required' => 'Tên danh mục là bắt buộc.',
            'name.string' => 'Tên danh mục phải là chuỗi ký tự.',
            'name.max' => 'Tên danh mục không được quá 255 ký tự.',
            'description.string' => 'Mô tả phải là chuỗi ký tự.',
            'description.max' => 'Mô tả không được quá 500 ký tự.',
        ]);

        $serviceCategory->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json([
            'message' => 'Danh mục dịch vụ đã được cập nhật thành công.',
            'data' => $serviceCategory
        ], 200);
    }
    // Xóa danh mục dịch vụ
    public function destroy(ServiceCategory $serviceCategory)
    {
        if ($serviceCategory->services()->count() > 0) {
            return response()->json([
                'message' => 'Không thể xóa danh mục vì vẫn còn dịch vụ liên kết.',
            ], 400);
        }

        $serviceCategory->delete();

        return response()->json([
            'message' => 'Danh mục dịch vụ đã được xóa thành công.',
        ], 200);
    }
}
