<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RoomController extends Controller
{
    // use AuthorizesRequests;

    // public function __construct()
    // {
    //     $this->authorizeResource(Room::class, 'rooms');
    // }

    public function index(Request $request)
    {
        $query = Room::query();

        if ($request->filled('room_number')) {
            $query->where('room_number', 'like', '%' . $request->room_number . '%');
        }

        if ($request->filled('room_type_id')) {
            $query->where('room_type_id', $request->room_type_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

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

        $rooms = $query->with([
            'roomType.amenities',
            'bookings.customer',
            'bookings.creator',
            'bookings.services'
        ])->whereNull('deleted_at')->get();

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

    public function show(Room $room)
    {
        $room->load(['roomType.amenities']);

        return response()->json([
            'message' => 'Thông tin phòng.',
            'data' => $room,
        ], 200);
    }

    public function store(Request $request)
    {
        try {
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

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $validator->errors(),
                ], 422);
            }

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

            $room->load(['roomType.amenities']);

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

    public function update(Request $request, Room $room)
    {
        try {
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

            if ($request->hasFile('image')) {
                if ($room->image && Storage::disk('public')->exists($room->image)) {
                    Storage::disk('public')->delete($room->image);
                }

                $imagePath = $request->file('image')->store('room_images', 'public');
                $room->image = $imagePath;
            }

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

    public function destroy(Room $room)
    {
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

    public function restore(Room $room)
    {
        if (!$room->trashed()) {
            return response()->json([
                'message' => 'Phòng chưa từng bị xóa.',
                'data' => $room
            ], 400);
        }

        $room->restore();

        return response()->json([
            'message' => 'Phòng đã được khôi phục thành công.',
            'data' => $room
        ], 200);
    }

    public function forceDelete(Room $room)
    {
        if (!$room->trashed()) {
            return response()->json([
                'message' => 'Phòng chưa bị xóa mềm nên không thể xóa vĩnh viễn.',
                'data' => $room
            ], 400);
        }

        try {
            if ($room->image && Storage::disk('public')->exists($room->image)) {
                Storage::disk('public')->delete($room->image);
            }

            $room->forceDelete();

            return response()->json([
                'message' => 'Phòng đã bị xóa vĩnh viễn khỏi hệ thống.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi xóa vĩnh viễn.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
