<?php

namespace App\Http\Controllers\Api;

use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\ServiceCategory;
use App\Http\Controllers\Controller;

class ServiceController extends Controller
{
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
    public function show($id)
    {
        $service = Service::with('category')->findOrFail($id);  // Lấy dịch vụ theo ID
        return response()->json([
            'message' => 'Thông tin dịch vụ',
            'data' => $service
        ], 200); // Trả về thông tin dịch vụ với thông báo thành công
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
    public function update(Request $request, $id)
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

        // Tìm dịch vụ theo ID
        $service = Service::findOrFail($id);

        // Cập nhật dịch vụ
        $service->update($request->all());

        // Trả về thông báo thành công
        return response()->json([
            'message' => 'Dịch vụ đã được cập nhật thành công.',
            'data' => $service
        ], 200);  // Trả về dịch vụ đã cập nhật
    }

    // Xóa dịch vụ
    public function destroy($id)
    {
        // Tìm dịch vụ theo ID
        $service = Service::findOrFail($id);

        // Xóa dịch vụ
        $service->delete();

        // Trả về thông báo thành công
        return response()->json([
            'message' => 'Dịch vụ đã được xóa thành công.'
        ], 204);  // Trả về trạng thái 204 No Content
    }
}
