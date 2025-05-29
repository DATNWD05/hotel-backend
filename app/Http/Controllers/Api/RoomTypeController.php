<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Exception;

class RoomTypeController extends Controller
{
    // Lấy tất cả loại phòng
    public function index()
    {
        $roomTypes = RoomType::all();

        // Nếu không có loại phòng nào
        if ($roomTypes->isEmpty()) {
            return response()->json([
                'message' => 'Không có loại phòng nào.',
                'data' => $roomTypes,
            ], 404);  // Trả về 404 nếu không có dữ liệu
        }

        // Trả về danh sách các loại phòng nếu có
        return response()->json([
            'message' => 'Danh sách loại phòng.',
            'data' => $roomTypes,
        ], 200);  // Trả về 200 khi có dữ liệu
    }


    // Lấy thông tin loại phòng theo ID
    public function show($id)
    {
        $roomType = RoomType::find($id);

        // Nếu không tìm thấy loại phòng theo ID
        if (!$roomType) {
            return response()->json([
                'message' => 'Loại phòng không tồn tại.',
                'data' => null,
            ], 404);  // Trả về 404 nếu không tìm thấy loại phòng
        }

        // Trả về thông tin loại phòng khi tìm thấy
        return response()->json([
            'message' => 'Thông tin loại phòng.',
            'data' => $roomType,
        ], 200);  // Trả về 200 khi tìm thấy loại phòng
    }


    // Tạo loại phòng mới
    public function store(Request $request)
    {
        try {
            // Validate với thông báo lỗi chi tiết
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
            ], [
                'name.required' => 'Tên loại phòng là bắt buộc.',
                'name.string' => 'Tên loại phòng phải là chuỗi.',
                'name.max' => 'Tên loại phòng không được vượt quá 255 ký tự.',
                'description.string' => 'Mô tả phải là chuỗi.',
                'description.max' => 'Mô tả không được vượt quá 500 ký tự.',
            ]);

            // Nếu validate không hợp lệ, trả về lỗi với thông báo chi tiết
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $validator->errors(),
                    'status' => 422
                ], 422);
            }

            // Nếu dữ liệu hợp lệ, tạo loại phòng mới
            $roomType = RoomType::create([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            return response()->json([
                'message' => 'Loại phòng đã được tạo thành công.',
                'data' => $roomType,
                'status' => 201
            ], 201); // Trả về 201 khi tạo thành công
        } catch (Exception $e) {
            // Trường hợp xảy ra lỗi khi tạo loại phòng
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi tạo loại phòng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500); // Trả về lỗi 500 khi có lỗi không mong muốn
        }
    }

    // Cập nhật loại phòng
    public function update(Request $request, $id)
    {
        $roomType = RoomType::find($id);

        // Kiểm tra nếu loại phòng không tồn tại
        if (!$roomType) {
            return response()->json([
                'message' => 'Loại phòng không tồn tại.',
                'data' => null
            ], 404);  // Trả về 404 nếu không tìm thấy loại phòng
        }

        try {
            // Validate dữ liệu với thông báo lỗi chi tiết
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
            ], [
                'name.required' => 'Tên loại phòng là bắt buộc.',
                'name.string' => 'Tên loại phòng phải là chuỗi.',
                'name.max' => 'Tên loại phòng không được vượt quá 255 ký tự.',
                'description.string' => 'Mô tả phải là chuỗi.',
                'description.max' => 'Mô tả không được vượt quá 500 ký tự.',
            ]);

            // Nếu validate thất bại, trả về thông báo lỗi chi tiết
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $validator->errors(),  // Trả về các lỗi validate chi tiết
                ], 422);  // Trả về lỗi 422 nếu dữ liệu không hợp lệ
            }

            // Cập nhật loại phòng nếu dữ liệu hợp lệ
            $roomType->update($validator->validated());

            return response()->json([
                'message' => 'Loại phòng đã được cập nhật.',
                'data' => $roomType
            ], 200);  // Trả về 200 khi cập nhật thành công
        } catch (Exception $e) {
            // Trả về thông báo lỗi nếu có lỗi trong quá trình xử lý
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật loại phòng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);  // Trả về lỗi 500 khi có lỗi không mong muốn
        }
    }

    // Xóa loại phòng
    public function destroy($id)
    {
        // Tìm loại phòng theo ID
        $roomType = RoomType::find($id);

        // Kiểm tra nếu loại phòng không tồn tại
        if (!$roomType) {
            return response()->json([
                'message' => 'Loại phòng không tồn tại.',
                'data' => null
            ], 404);  // Trả về 404 nếu không tìm thấy loại phòng
        }

        try {
            // Tiến hành xóa loại phòng
            $roomType->delete();

            return response()->json([
                'message' => 'Loại phòng đã được xóa thành công.',
                'data' => null
            ], 200);  // Trả về 200 khi xóa thành công
        } catch (Exception $e) {
            // Trả về thông báo lỗi nếu có lỗi khi xóa
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi xóa loại phòng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);  // Trả về lỗi 500 khi có lỗi không mong muốn
        }
    }
}
