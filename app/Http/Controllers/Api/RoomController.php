<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Floor;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Models\Room;

class RoomController extends Controller
{
    // Lấy tất cả phòng hoặc tìm kiếm phòng theo các tiêu chí
    public function index(Request $request)
    {
        // Tạo query cơ bản cho phòng
        $query = Room::query();

        // Tìm kiếm theo số phòng
        if ($request->filled('room_number')) {
            $query->where('room_number', 'like', '%' . $request->room_number . '%');
        }

        // Tìm kiếm theo loại phòng
        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }

        // Tìm kiếm theo tầng
        if ($request->filled('floor_id')) {
            $query->where('floor_id', $request->floor_id);
        }

        // Tìm kiếm theo trạng thái
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Tìm kiếm theo khoảng giá (min_price và max_price)
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Lấy tất cả phòng nếu không có tìm kiếm
        $rooms = $query->with(['roomType', 'floor'])->get();

        // Nếu không có phòng nào thỏa mãn các tiêu chí tìm kiếm
        if ($rooms->isEmpty()) {
            return response()->json([
                'message' => 'Không có phòng nào thỏa mãn các tiêu chí tìm kiếm.',
                'data' => $rooms,
            ], 404);  // Trả về 404 nếu không có dữ liệu
        }

        // Trả về danh sách phòng nếu có kết quả tìm kiếm hoặc không có điều kiện tìm kiếm
        return response()->json([
            'message' => 'Danh sách phòng theo tiêu chí tìm kiếm hoặc tất cả phòng.',
            'data' => $rooms,
        ], 200);  // Trả về 200 khi có dữ liệu
    }

    // Lấy thông tin phòng theo ID
    public function show($id)
    {
        $room = Room::with(['roomType', 'floor'])->find($id);

        // Nếu không tìm thấy phòng theo ID
        if (!$room) {
            return response()->json([
                'message' => 'Phòng không tồn tại.',
                'data' => null,
            ], 404);  // Trả về 404 nếu không tìm thấy phòng
        }

        // Trả về thông tin phòng khi tìm thấy
        return response()->json([
            'message' => 'Thông tin phòng.',
            'data' => $room,
        ], 200);  // Trả về 200 khi tìm thấy phòng
    }

    // Tạo phòng mới
    public function store(Request $request)
    {
        try {
            // Validate với thông báo lỗi chi tiết
            $validator = Validator::make($request->all(), [
                'room_number' => 'required|string|max:255',
                'room_type_id' => 'required|integer|exists:room_types,id',
                'floor_id' => 'required|integer|exists:floors,id',
                'price' => 'required|numeric',
                'status' => 'required|string|in:available,booked,cleaning,maintenance',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ], [
                'room_number.required' => 'Số phòng là bắt buộc.',
                'room_type_id.required' => 'Loại phòng là bắt buộc.',
                'floor_id.required' => 'Tầng là bắt buộc.',
                'price.required' => 'Giá phòng là bắt buộc.',
                'status.required' => 'Trạng thái phòng là bắt buộc.',
                'image.image' => 'Ảnh phải là một tệp hình ảnh.',
            ]);

            // Nếu validate thất bại, trả về thông báo lỗi chi tiết
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $validator->errors(),  // Trả về các lỗi validate chi tiết
                ], 422);  // Trả về lỗi 422 nếu dữ liệu không hợp lệ
            }

            // Lưu ảnh nếu có
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('room_images', 'public');
            }

            // Tạo phòng mới
            $room = Room::create([
                'room_number' => $request->room_number,
                'room_type_id' => $request->room_type_id,
                'floor_id' => $request->floor_id,
                'price' => $request->price,
                'status' => $request->status,
                'image' => $imagePath,  // Lưu đường dẫn ảnh vào cơ sở dữ liệu
            ]);

            // Tải thông tin liên quan đến loại phòng và tầng
            $room->load(['roomType', 'floor']); // Tải thông tin liên quan đến room_type và floor

            return response()->json([
                'message' => 'Phòng đã được tạo thành công.',
                'data' => $room,
            ], 201);  // Trả về 201 khi tạo thành công
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi tạo phòng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);  // Trả về lỗi 500 khi có lỗi không mong muốn
        }
    }


    // Cập nhật thông tin phòng
    public function update(Request $request, $id)
    {
        $room = Room::find($id);

        // Kiểm tra nếu phòng không tồn tại
        if (!$room) {
            return response()->json([
                'message' => 'Phòng không tồn tại.',
                'data' => null
            ], 404);  // Trả về 404 nếu không tìm thấy phòng
        }

        try {
            // Validate với thông báo lỗi chi tiết
            $validator = Validator::make($request->all(), [
                'room_number' => 'required|string|max:255',
                'room_type_id' => 'required|integer|exists:room_types,id',
                'floor_id' => 'required|integer|exists:floors,id',
                'price' => 'required|numeric',
                'status' => 'required|string|in:available,booked,cleaning,maintenance',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ], [
                'room_number.required' => 'Số phòng là bắt buộc.',
                'room_type_id.required' => 'Loại phòng là bắt buộc.',
                'floor_id.required' => 'Tầng là bắt buộc.',
                'price.required' => 'Giá phòng là bắt buộc.',
                'status.required' => 'Trạng thái phòng là bắt buộc.',
                'image.image' => 'Ảnh phải là một tệp hình ảnh.',
            ]);

            // Nếu validate thất bại, trả về thông báo lỗi chi tiết
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $validator->errors(),
                ], 422);  // Trả về lỗi 422 nếu dữ liệu không hợp lệ
            }

            // Cập nhật ảnh nếu có
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('room_images', 'public');
                $room->image = $imagePath;
            }

            // Cập nhật thông tin phòng
            $room->update([
                'room_number' => $request->room_number,
                'room_type_id' => $request->room_type_id,
                'floor_id' => $request->floor_id,
                'price' => $request->price,
                'status' => $request->status,
            ]);

            // Tải thông tin liên quan đến loại phòng và tầng
            $room->load(['roomType', 'floor']);  // Tải thông tin loại phòng và tầng

            return response()->json([
                'message' => 'Phòng đã được cập nhật thành công.',
                'data' => $room,
            ], 200);  // Trả về 200 khi cập nhật thành công
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật phòng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);  // Trả về lỗi 500 khi có lỗi không mong muốn
        }
    }


    // Xóa phòng
    public function destroy($id)
    {
        $room = Room::find($id);

        // Kiểm tra nếu phòng không tồn tại
        if (!$room) {
            return response()->json([
                'message' => 'Phòng không tồn tại.',
                'data' => null
            ], 404);  // Trả về 404 nếu không tìm thấy phòng
        }

        try {
            // Tiến hành xóa phòng
            $room->delete();

            return response()->json([
                'message' => 'Phòng đã được xóa thành công.',
                'data' => null
            ], 200);  // Trả về 200 khi xóa thành công
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi xóa phòng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);  // Trả về lỗi 500 khi có lỗi không mong muốn
        }
    }
}
