<?php

namespace App\Http\Controllers\Api;

use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\ServiceCategory;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ServiceController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Service::class, 'service');
    }
    // Lấy tất cả dịch vụ
    public function index()
    {
        $data = Service::with('category')->paginate(10);

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

    // Lấy dịch vụ theo ID
    public function show(Service $service)
    {
        $service->load('category');

        return response()->json([
            'message' => 'Thông tin dịch vụ',
            'data' => $service
        ], 200);
    }


    // Tạo mới dịch vụ
    public function store(Request $request)
    {
        // Xác thực dữ liệu đầu vào
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:service_categories,id',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
        ], [
            'name.required' => 'Tên dịch vụ là bắt buộc.',
            'category_id.required' => 'Danh mục dịch vụ là bắt buộc.',
            'category_id.exists' => 'Danh mục dịch vụ phải tồn tại.',
            'price.required' => 'Giá dịch vụ là bắt buộc.',
            'price.numeric' => 'Giá dịch vụ phải là số.',
        ]);

        // Tạo dịch vụ mới
        $service = Service::create($request->all());

        // Trả về thông báo thành công
        return response()->json([
            'message' => 'Dịch vụ đã được tạo thành công.',
            'data' => $service
        ], 201); // Trả về dịch vụ mới tạo với thông báo thành công
    }

    // Cập nhật dịch vụ
    public function update(Request $request, Service $service)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:service_categories,id',
            'description' => 'nullable|string',
            'price' => 'required|numeric',
        ], [
            'name.required' => 'Tên dịch vụ là bắt buộc.',
            'category_id.required' => 'Danh mục dịch vụ là bắt buộc.',
            'category_id.exists' => 'Danh mục dịch vụ phải tồn tại.',
            'price.required' => 'Giá dịch vụ là bắt buộc.',
            'price.numeric' => 'Giá dịch vụ phải là số.',
        ]);

        $service->update($request->all());

        return response()->json([
            'message' => 'Dịch vụ đã được cập nhật thành công.',
            'data' => $service
        ], 200);
    }

    // Xóa dịch vụ
    public function destroy(Service $service)
    {
        $service->delete();

        return response()->json([
            'message' => 'Dịch vụ đã được xóa thành công.'
        ], 204);
    }
}
