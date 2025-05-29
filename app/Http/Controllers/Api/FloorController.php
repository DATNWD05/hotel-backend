<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Floor;
use Illuminate\Support\Facades\Validator;
use Exception;

class FloorController extends Controller
{
    // Lấy tất cả tầng
    public function index()
    {
        $floors = Floor::all();

        // Nếu không có tầng nào
        if ($floors->isEmpty()) {
            return response()->json([
                'message' => 'Không có tầng nào.',
                'data' => $floors,
            ], 404);  // Trả về 404 nếu không có dữ liệu
        }

        // Trả về danh sách các tầng nếu có
        return response()->json([
            'message' => 'Danh sách tầng.',
            'data' => $floors,
        ], 200);  // Trả về 200 khi có dữ liệu
    }

    // Lấy thông tin tầng theo ID
    public function show($id)
    {
        $floor = Floor::find($id);

        // Nếu không tìm thấy tầng theo ID
        if (!$floor) {
            return response()->json([
                'message' => 'Tầng không tồn tại.',
                'data' => null,
            ], 404);  // Trả về 404 nếu không tìm thấy tầng
        }

        // Trả về thông tin tầng khi tìm thấy
        return response()->json([
            'message' => 'Thông tin tầng.',
            'data' => $floor,
        ], 200);  // Trả về 200 khi tìm thấy tầng
    }

    // Tạo tầng mới
    public function store(Request $request)
    {
        try {
            // Validate với thông báo lỗi chi tiết
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'number' => 'required|integer',
            ], [
                'name.required' => 'Tên tầng là bắt buộc.',
                'name.string' => 'Tên tầng phải là chuỗi.',
                'name.max' => 'Tên tầng không được vượt quá 255 ký tự.',
                'number.required' => 'Số tầng là bắt buộc.',
                'number.integer' => 'Số tầng phải là một số nguyên.',
            ]);

            // Nếu validate thất bại, trả về thông báo lỗi chi tiết
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $validator->errors(),  // Trả về các lỗi validate chi tiết
                ], 422);  // Trả về lỗi 422 nếu dữ liệu không hợp lệ
            }

            // Tạo mới tầng
            $floor = Floor::create([
                'name' => $request->name,
                'number' => $request->number,
            ]);

            return response()->json([
                'message' => 'Tầng đã được tạo thành công.',
                'data' => $floor,
            ], 201);  // Trả về 201 khi tạo thành công
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi tạo tầng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);  // Trả về lỗi 500 khi có lỗi không mong muốn
        }
    }

    // Cập nhật thông tin tầng
    public function update(Request $request, $id)
    {
        $floor = Floor::find($id);

        // Kiểm tra nếu tầng không tồn tại
        if (!$floor) {
            return response()->json([
                'message' => 'Tầng không tồn tại.',
                'data' => null
            ], 404);  // Trả về 404 nếu không tìm thấy tầng
        }

        try {
            // Validate với thông báo lỗi chi tiết
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'number' => 'required|integer',
            ], [
                'name.required' => 'Tên tầng là bắt buộc.',
                'name.string' => 'Tên tầng phải là chuỗi.',
                'name.max' => 'Tên tầng không được vượt quá 255 ký tự.',
                'number.required' => 'Số tầng là bắt buộc.',
                'number.integer' => 'Số tầng phải là một số nguyên.',
            ]);

            // Nếu validate thất bại, trả về thông báo lỗi chi tiết
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $validator->errors(),  // Trả về các lỗi validate chi tiết
                ], 422);  // Trả về lỗi 422 nếu dữ liệu không hợp lệ
            }

            // Cập nhật thông tin tầng
            $floor->update([
                'name' => $request->name,
                'number' => $request->number,
            ]);

            return response()->json([
                'message' => 'Tầng đã được cập nhật thành công.',
                'data' => $floor,
            ], 200);  // Trả về 200 khi cập nhật thành công
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật tầng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);  // Trả về lỗi 500 khi có lỗi không mong muốn
        }
    }

    // Xóa tầng
    public function destroy($id)
    {
        $floor = Floor::find($id);

        // Kiểm tra nếu tầng không tồn tại
        if (!$floor) {
            return response()->json([
                'message' => 'Tầng không tồn tại.',
                'data' => null
            ], 404);  // Trả về 404 nếu không tìm thấy tầng
        }

        try {
            // Tiến hành xóa tầng
            $floor->delete();

            return response()->json([
                'message' => 'Tầng đã được xóa thành công.',
                'data' => null
            ], 200);  // Trả về 200 khi xóa thành công
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi xóa tầng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);  // Trả về lỗi 500 khi có lỗi không mong muốn
        }
    }
}