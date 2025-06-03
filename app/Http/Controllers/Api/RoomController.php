<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
// use App\Models\Floor;
use App\Models\Room;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

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
        // if ($request->filled('floor_id')) {
        //     $query->where('floor_id', $request->floor_id);
        // }

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
        // $rooms = $query->with(['roomType', 'floor'])->get();
        $rooms = $query->with(['roomType'])->get();

        if ($rooms->isEmpty()) {
            return response()->json([
                'message' => 'Không có phòng nào thỏa mãn các tiêu chí tìm kiếm.',
                'data' => $rooms,
            ], 404);
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

        if (!$room) {
            return response()->json([
                'message' => 'Phòng không tồn tại.',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Thông tin phòng.',
            'data' => $room,
        ], 200);
    }

    // Tạo phòng mới
    public function store(Request $request)
    {
        try {
            // Validate với thông báo lỗi chi tiết
            $validator = Validator::make($request->all(), [
                'room_number' => 'required|string|max:255',
                'room_type_id' => 'required|integer|exists:room_types,id',
                // 'floor_id' => 'required|integer|exists:floors,id',
                'price' => 'required|numeric',
                'status' => 'required|string|in:available,booked,cleaning,maintenance',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ], [
                'room_number.required' => 'Số phòng là bắt buộc.',
                'room_type_id.required' => 'Loại phòng là bắt buộc.',
                // 'floor_id.required' => 'Tầng là bắt buộc.',
                'price.required' => 'Giá phòng là bắt buộc.',
                'status.required' => 'Trạng thái phòng là bắt buộc.',
                'image.image' => 'Ảnh phải là một tệp hình ảnh.',
            ]);

            // Nếu validate thất bại, trả về thông báo lỗi chi tiết
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Lưu ảnh nếu có
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('room_images', 'public');
            }

            $room = Room::create([
                'room_number' => $request->room_number,
                'room_type_id' => $request->room_type_id,
                // 'floor_id' => $request->floor_id,
                'price' => $request->price,
                'status' => $request->status,
                'image' => $imagePath,
            ]);

            $room->load(['roomType']); // Tải thông tin liên quan đến room_type

            return response()->json([
                'message' => 'Phòng đã được tạo thành công.',
                'data' => $room,
            ], 201);
        } catch (Exception $e) {
            if ($e instanceof QueryException && $e->errorInfo[1] == 1062) {
                return response()->json([
                    'message' => 'Số phòng đã tồn tại.',
                    'error' => $e->getMessage()
                ], 409);
            }

            return response()->json([
                'message' => 'Đã xảy ra lỗi khi tạo phòng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }



    // Cập nhật thông tin phòng
    public function update(Request $request, $id)
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json([
                'message' => 'Phòng không tồn tại.',
                'data' => null
            ], 404);
        }

        try {
            // Validate đầu vào
            $validator = Validator::make($request->all(), [
                'room_number' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('rooms')->ignore($room->id),
                ],
                'room_type_id' => 'required|integer|exists:room_types,id',
                // 'floor_id' => 'required|integer|exists:floors,id',
                'price' => 'required|numeric',
                'status' => 'required|string|in:available,booked,cleaning,maintenance',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ], [
                'room_number.required' => 'Số phòng là bắt buộc.',
                'room_number.unique' => 'Số phòng đã tồn tại.',
                'room_type_id.required' => 'Loại phòng là bắt buộc.',
                // 'floor_id.required' => 'Tầng là bắt buộc.',
                'price.required' => 'Giá phòng là bắt buộc.',
                'status.required' => 'Trạng thái phòng là bắt buộc.',
                'image.image' => 'Ảnh phải là một tệp hình ảnh.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Cập nhật ảnh nếu có
            if ($request->hasFile('image')) {
                // Xóa ảnh cũ nếu có
                if ($room->image && Storage::disk('public')->exists($room->image)) {
                    Storage::disk('public')->delete($room->image);
                }

                // Lưu ảnh mới
                $imagePath = $request->file('image')->store('room_images', 'public');
                $room->image = $imagePath;
            }

            // Cập nhật thông tin phòng
            $room->update([
                'room_number' => $request->room_number,
                'room_type_id' => $request->room_type_id,
                // 'floor_id' => $request->floor_id,
                'price' => $request->price,
                'status' => $request->status,
            ]);

            $room->load(['roomType']);

            return response()->json([
                'message' => 'Phòng đã được cập nhật thành công.',
                'data' => $room,
            ], 200);
        } catch (Exception $e) {
            if ($e instanceof QueryException && $e->errorInfo[1] == 1062) {
                return response()->json([
                    'message' => 'Số phòng đã tồn tại.',
                    'error' => $e->getMessage()
                ], 409);
            }

            return response()->json([
                'message' => 'Đã xảy ra lỗi khi tạo phòng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    // Xóa phòng
    public function destroy($id)
    {
        $room = Room::find($id);

        if (!$room) {
            return response()->json([
                'message' => 'Phòng không tồn tại.',
                'data' => null
            ], 404);
        }

        try {
            // Xóa ảnh nếu tồn tại
            if ($room->image && Storage::disk('public')->exists($room->image)) {
                Storage::disk('public')->delete($room->image);
            }

            $room->delete();

            return response()->json([
                'message' => 'Phòng đã được xóa thành công.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi xóa phòng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }
}
