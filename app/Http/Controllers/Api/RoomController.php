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
use App\Models\Booking;

class RoomController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Room::class, 'rooms');
    }

    /**
     * Lấy danh sách phòng với các bộ lọc và phân trang.
     */
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
                $q->where('base_rate', '>=', $request->min_price);
            });
        }

        if ($request->filled('max_price')) {
            $query->whereHas('roomType', function ($q) use ($request) {
                $q->where('base_rate', '<=', $request->max_price);
            });
        }

        $rooms = $query->with([
            'roomType.amenities',
            'bookings.customer',
            'bookings.creator',
            'bookings.services'
        ])
            ->whereNull('deleted_at')
            ->paginate(10); // Phân trang với 10 bản ghi mỗi trang

        if ($rooms->isEmpty()) {
            return response()->json([
                'message' => 'Không có phòng nào thỏa mãn các tiêu chí tìm kiếm.',
                'data' => [],
            ], 200);
        }

        return response()->json([
            'message' => 'Danh sách phòng theo tiêu chí tìm kiếm.',
            'data' => $rooms->items(),
            'pagination' => [
                'current_page' => $rooms->currentPage(),
                'total_pages' => $rooms->lastPage(),
                'total_items' => $rooms->total(),
                'per_page' => $rooms->perPage(),
            ],
        ], 200);
    }

    /**
     * Hiển thị chi tiết một phòng.
     */
    public function show(Room $room)
    {
        $room->load(['roomType.amenities']);

        return response()->json([
            'message' => 'Thông tin phòng.',
            'data' => $room,
        ], 200);
    }

    /**
     * Tạo phòng mới.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'room_number' => 'required|string|max:255|unique:rooms,room_number',
                'room_type_id' => 'required|integer|exists:room_types,id',
                'status' => 'required|string|in:available,cleaning,maintenance',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ], [
                'room_number.required' => 'Số phòng là bắt buộc.',
                'room_number.unique' => 'Số phòng đã tồn tại.',
                'room_type_id.required' => 'Loại phòng là bắt buộc.',
                'room_type_id.exists' => 'Loại phòng không tồn tại.',
                'status.required' => 'Trạng thái phòng là bắt buộc.',
                'status.in' => 'Trạng thái phòng phải là available, cleaning, hoặc maintenance.',
                'image.image' => 'Ảnh phải là một tệp hình ảnh.',
                'image.mimes' => 'Ảnh phải có định dạng jpeg, png, jpg hoặc gif.',
                'image.max' => 'Ảnh không được vượt quá 2MB.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            if ($request->status === 'booked') {
                return response()->json([
                    'message' => 'Không thể tạo phòng với trạng thái booked. Trạng thái này chỉ được đặt khi có booking hợp lệ.',
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
                    'error' => $e->getMessage(),
                ], 409);
            }

            return response()->json([
                'message' => 'Đã xảy ra lỗi khi tạo phòng.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cập nhật thông tin phòng.
     */
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
                'room_type_id.exists' => 'Loại phòng không tồn tại.',
                'status.required' => 'Trạng thái phòng là bắt buộc.',
                'status.in' => 'Trạng thái phòng phải là available, booked, cleaning, hoặc maintenance.',
                'image.image' => 'Ảnh phải là một tệp hình ảnh.',
                'image.mimes' => 'Ảnh phải có định dạng jpeg, png, jpg hoặc gif.',
                'image.max' => 'Ảnh không được vượt quá 2MB.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            if ($request->status === 'booked') {
                $hasActiveBooking = Booking::whereHas('rooms', function ($query) use ($room) {
                    $query->where('room_id', $room->id);
                })
                    ->whereIn('status', ['Pending', 'Confirmed', 'Checked-in'])
                    ->exists();

                if (!$hasActiveBooking) {
                    return response()->json([
                        'message' => 'Không thể đặt trạng thái booked vì phòng không có booking hợp lệ.',
                    ], 422);
                }
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
                    'error' => $e->getMessage(),
                ], 409);
            }

            return response()->json([
                'message' => 'Đã xảy ra lỗi khi cập nhật phòng.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Xóa mềm phòng.
     */
    public function destroy(Room $room)
    {
        try {
            // Kiểm tra xem phòng có booking đang hoạt động hay không
            $hasActiveBooking = Booking::whereHas('rooms', function ($query) use ($room) {
                $query->where('room_id', $room->id);
            })
                ->whereIn('status', ['Pending', 'Confirmed', 'Checked-in'])
                ->exists();

            if ($hasActiveBooking) {
                return response()->json([
                    'message' => 'Không thể xóa phòng vì phòng đang có booking hoạt động.',
                ], 422);
            }

            $room->delete();

            return response()->json([
                'message' => 'Phòng đã được xóa (mềm) thành công.',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi xóa phòng.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Khôi phục phòng đã bị xóa mềm.
     */
    public function restore(Room $room)
    {
        if (!$room->trashed()) {
            return response()->json([
                'message' => 'Phòng chưa từng bị xóa.',
                'data' => $room,
            ], 400);
        }

        try {
            $room->restore();
            $room->load(['roomType.amenities']);

            return response()->json([
                'message' => 'Phòng đã được khôi phục thành công.',
                'data' => $room,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi khôi phục phòng.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Xóa vĩnh viễn phòng.
     */
    public function forceDelete(Room $room)
    {
        if (!$room->trashed()) {
            return response()->json([
                'message' => 'Phòng chưa bị xóa mềm nên không thể xóa vĩnh viễn.',
                'data' => $room,
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
                'message' => 'Đã xảy ra lỗi khi xóa vĩnh viễn phòng.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lấy danh sách phòng đã bị xóa mềm.
     */
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
            'data' => $rooms,
        ], 200);
    }

    /**
     * Lấy danh sách phòng khả dụng dựa trên khoảng thời gian.
     */
    public function getAvailableRooms(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'check_in_date' => 'required|date|after_or_equal:today',
                'check_out_date' => 'required|date|after:check_in_date',
                'room_type_id' => 'sometimes|exists:room_types,id',
            ], [
                'check_in_date.required' => 'Ngày nhận phòng là bắt buộc.',
                'check_in_date.date' => 'Ngày nhận phòng phải là định dạng ngày hợp lệ.',
                'check_in_date.after_or_equal' => 'Ngày nhận phòng phải từ hôm nay trở đi.',
                'check_out_date.required' => 'Ngày trả phòng là bắt buộc.',
                'check_out_date.date' => 'Ngày trả phòng phải là định dạng ngày hợp lệ.',
                'check_out_date.after' => 'Ngày trả phòng phải sau ngày nhận phòng.',
                'room_type_id.exists' => 'Loại phòng không tồn tại.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Dữ liệu không hợp lệ.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $query = Room::where('status', '!=', 'maintenance')
                ->whereNull('deleted_at')
                ->with('roomType');

            $rooms = $query->get()->filter(function ($room) use ($request) {
                return $room->isAvailable($request->check_in_date, $request->check_out_date);
            });

            return response()->json([
                'message' => 'Danh sách phòng khả dụng.',
                'data' => $rooms->values()->map(function ($room) {
                    return [
                        'id' => $room->id,
                        'room_number' => $room->room_number,
                        'status' => $room->status,
                        'room_type_id' => $room->room_type_id,
                        'base_rate' => $room->roomType->base_rate ?? 0,
                    ];
                }),
                'total' => $rooms->count(),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi lấy phòng khả dụng.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
