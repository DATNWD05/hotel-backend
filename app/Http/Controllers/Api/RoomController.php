<?php

namespace App\Http\Controllers\Api;

use Exception;
// use App\Models\Floor;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RoomController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Room::class, 'rooms');
    }
    // Lấy tất cả phòng hoặc tìm kiếm phòng theo các tiêu chí
    public function index(Request $request)
    {
        // Tạo query builder cho Room
        $query = Room::query();

        // Tìm kiếm theo số phòng
        if ($request->filled('room_number')) {
            $query->where('room_number', 'like', '%' . $request->room_number . '%');
        }

        // Tìm kiếm theo loại phòng
        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }

        // Tìm kiếm theo trạng thái phòng
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Tìm kiếm theo khoảng giá
        if ($request->filled('min_price')) {
            $query->whereHas('roomType', function ($q) use ($request) {
                $q->where('price', '>=', $request->min_price);
            });
        }

        if ($request->filled('max_price')) {
            $query->whereHas('roomType', function ($q) use ($request) {
                $q->where('price', '<=', $request->max_price);
            });
        }

        // Eager load quan hệ và loại trừ soft deleted
        $rooms = $query->with([
            'roomType.amenities',
            'bookings.customer',
            'bookings.creator',
            'bookings.services'
        ])
            ->whereNull('deleted_at')
            ->get();

        // Trả về kết quả
        if ($rooms->isEmpty()) {
            return response()->json([
                'message' => 'Không có phòng nào thỏa mãn các tiêu chí tìm kiếm.',
                'data' => [],
            ], 404);
        }

        return response()->json([
            'message' => 'Danh sách phòng theo tiêu chí tìm kiếm hoặc tất cả phòng.',
            'data' => $rooms,
        ], 200);
    }


    // Lấy thông tin phòng theo ID
    public function show($id)
    {
        // $room = Room::with(['roomType.amenities', 'bookings.customer', 'bookings.creator'])->find($id);
        $room = Room::with(['roomType.amenities'])->find($id);


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
                'status' => 'required|string|in:available,booked,cleaning,maintenance',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ], [
                'room_number.required' => 'Số phòng là bắt buộc.',
                'room_type_id.required' => 'Loại phòng là bắt buộc.',
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
                'status' => $request->status,
                'image' => $imagePath,
            ]);

            $room->load(['roomType.amenities']); // Tải thông tin liên quan đến room_type

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
                'status' => 'required|string|in:available,booked,cleaning,maintenance',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ], [
                'room_number.required' => 'Số phòng là bắt buộc.',
                'room_number.unique' => 'Số phòng đã tồn tại.',
                'room_type_id.required' => 'Loại phòng là bắt buộc.',
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
                'status' => $request->status,
            ]);

            $room->load(['roomType.amenities']);

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
                'message' => 'Đã xảy ra lỗi khi sửa phòng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    // Xóa phòng mềm
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

            $room->delete();

            return response()->json([
                'message' => 'Phòng đã được xóa (mềm) thành công.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi xóa phòng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function restore($id)
    {
        $room = Room::withTrashed()->find($id);

        if (!$room) {
            return response()->json([
                'message' => 'Phòng không tồn tại hoặc chưa từng bị xóa.',
                'data' => null
            ], 404);
        }

        $room->restore();

        return response()->json([
            'message' => 'Phòng đã được khôi phục thành công.',
            'data' => $room
        ], 200);
    }

    public function forceDelete($id)
    {
        $room = Room::withTrashed()->find($id);

        if (!$room) {
            return response()->json([
                'message' => 'Phòng không tồn tại.',
                'data' => null
            ], 404);
        }

        // Kiểm tra đã bị xóa mềm chưa
        if (!$room->trashed()) {
            return response()->json([
                'message' => 'Phòng chưa bị xóa mềm nên không thể xóa vĩnh viễn.',
                'data' => $room
            ], 400);
        }

        try {
            // Xóa ảnh nếu có
            if ($room->image && Storage::disk('public')->exists($room->image)) {
                Storage::disk('public')->delete($room->image);
            }

            $room->forceDelete();

            return response()->json([
                'message' => 'Phòng đã bị xóa vĩnh viễn khỏi hệ thống.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi xóa vĩnh viễn.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Lấy danh sách phòng đã xóa mềm
    public function trashed()
    {
        $rooms = Room::onlyTrashed()->with(['roomType.amenities'])->get();

        if ($rooms->isEmpty()) {
            return response()->json([
                'message' => 'Không có phòng nào đã bị xóa.',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'message' => 'Danh sách phòng đã bị xóa mềm.',
            'data' => $rooms
        ], 200);
    }
}
