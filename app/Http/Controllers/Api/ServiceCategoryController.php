<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Models\ServiceCategory;
use App\Http\Controllers\Controller;

class ServiceCategoryController extends Controller
{
    // Lấy tất cả danh mục dịch vụ
    public function index()
    {
        $categories = ServiceCategory::all();  // Lấy tất cả các danh mục dịch vụ
        return response()->json([
            'message' => 'Danh sách tất cả danh mục dịch vụ',
            'data' => $categories
        ], 200); // Trả về danh sách danh mục dịch vụ với thông báo thành công
    }

    // Lấy danh mục dịch vụ theo ID
    public function show($id)
    {
        $category = ServiceCategory::findOrFail($id);  // Lấy danh mục dịch vụ theo ID
        return response()->json([
            'message' => 'Thông tin danh mục dịch vụ',
            'data' => $category
        ], 200);  // Trả về thông tin danh mục dịch vụ với thông báo thành công
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
    public function update(Request $request, $id)
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

        // Tìm danh mục dịch vụ theo ID
        $category = ServiceCategory::findOrFail($id);

        // Cập nhật dữ liệu
        $category->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        // Trả về thông báo thành công
        return response()->json([
            'message' => 'Danh mục dịch vụ đã được cập nhật thành công.',
            'data' => $category
        ], 200);  // Trả về thông tin danh mục đã cập nhật
    }

    // Xóa danh mục dịch vụ
    public function destroy($id)
    {
        // Tìm danh mục dịch vụ theo ID
        $category = ServiceCategory::findOrFail($id);

        // Xóa danh mục dịch vụ
        $category->delete();

        // Trả về thông báo thành công
        return response()->json([
            'message' => 'Danh mục dịch vụ đã được xóa thành công.',
        ], 204);  // Trả về trạng thái 204 No Content
    }
}
