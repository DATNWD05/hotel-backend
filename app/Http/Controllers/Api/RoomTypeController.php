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
            ], 404);
        }

        // Kiểm tra xem loại phòng có phòng nào đang sử dụng không
        if ($roomType->rooms()->exists()) {
            return response()->json([
                'message' => 'Không thể xóa loại phòng đang có phòng sử dụng.',
                'data' => null
            ], 400);
        }

        try {
            $roomType->delete();

            return response()->json([
                'message' => 'Loại phòng đã được xóa thành công.',
                'data' => null
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Đã xảy ra lỗi khi xóa loại phòng.',
                'error' => $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function syncAmenities(Request $request, RoomType $room_type)
    {
        try {
            $request->validate([
                'amenities' => [
                    'required',
                    'array',
                    function ($attribute, $value, $fail) {
                        if (empty($value)) {
                            $fail('Danh sách amenities không được để trống.');
                        }
                    },
                ],
                'amenities.*.id' => [
                    'required',
                    'integer',
                    'exists:amenities,id',
                    function ($attribute, $value, $fail) {
                        if (!is_numeric($value) || $value <= 0) {
                            $fail('ID của amenity phải là số nguyên dương.');
                        }
                    },
                ],
                'amenities.*.quantity' => [
                    'required',
                    'integer',
                    'min:1',
                    'max:100', // Giới hạn tối đa, tùy chỉnh theo nhu cầu
                    function ($attribute, $value, $fail) {
                        if (!is_numeric($value)) {
                            $fail('Quantity phải là số.');
                        }
                    },
                ],
            ], [
                'amenities.required' => 'Danh sách amenities là bắt buộc.',
                'amenities.array' => 'Danh sách amenities phải là một mảng.',
                'amenities.*.id.required' => 'ID của amenity là bắt buộc.',
                'amenities.*.id.exists' => 'Amenity với ID :input không tồn tại.',
                'amenities.*.id.integer' => 'ID của amenity phải là số nguyên.',
                'amenities.*.quantity.required' => 'Quantity là bắt buộc.',
                'amenities.*.quantity.integer' => 'Quantity phải là số nguyên.',
                'amenities.*.quantity.min' => 'Quantity phải lớn hơn hoặc bằng :min.',
                'amenities.*.quantity.max' => 'Quantity không được vượt quá :max.',
            ]);

            // Chuyển sang định dạng [amenity_id => ['quantity' => X], ...]
            $syncData = [];
            foreach ($request->input('amenities') as $item) {
                $syncData[$item['id']] = ['quantity' => $item['quantity']];
            }

            // Đồng bộ pivot
            $room_type->amenities()->sync($syncData);

            // Lấy lại danh sách amenities (kèm quantity) mới nhất
            $updatedList = $room_type->amenities()->get();

            return response()->json([
                'status' => 'success',
                'data'   => $updatedList
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
