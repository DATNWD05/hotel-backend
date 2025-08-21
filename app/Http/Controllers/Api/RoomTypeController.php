<?php

namespace App\Http\Controllers\Api;

use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RoomTypeController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(RoomType::class, 'room_type');
    }

    public function index()
    {
        $types = RoomType::with('amenities')->get();

        return response()->json([
            'message' => 'Danh sách loại phòng.',
            'data' => $types,
        ]);
    }

    public function store(Request $request)
    {
        $messages = [
            'code.required' => 'Mã loại phòng là bắt buộc.',
            'code.string'   => 'Mã loại phòng phải là chuỗi ký tự.',
            'code.max'      => 'Mã loại phòng không được vượt quá 20 ký tự.',
            'code.unique'   => 'Mã loại phòng đã tồn tại.',

            'name.required' => 'Tên loại phòng là bắt buộc.',
            'name.string'   => 'Tên loại phòng phải là chuỗi ký tự.',
            'name.max'      => 'Tên loại phòng không được vượt quá 255 ký tự.',
            'name.unique'   => 'Tên loại phòng đã tồn tại.',

            'description.string' => 'Mô tả loại phòng phải là chuỗi ký tự.',

            'max_occupancy.required' => 'Sức chứa tối đa là bắt buộc.',
            'max_occupancy.integer'  => 'Sức chứa tối đa phải là số nguyên.',
            'max_occupancy.min'      => 'Sức chứa tối thiểu phải từ 0.',
            'max_occupancy.max'      => 'Sức chứa tối đa không vượt quá 255.',

            'base_rate.required' => 'Giá cơ bản là bắt buộc.',
            'base_rate.numeric'  => 'Giá cơ bản phải là số.',
            'base_rate.min'      => 'Giá cơ bản phải từ 100.000 VNĐ trở lên.',

            'hourly_rate.required' => 'Giá theo giờ là bắt buộc.',
            'hourly_rate.numeric'  => 'Giá theo giờ phải là số.',
            'hourly_rate.min'      => 'Giá theo giờ phải từ 100.000 VNĐ trở lên.',
        ];

        $validator = Validator::make($request->all(), [
            'code'         => 'required|string|max:20|unique:room_types,code',
            'name'         => 'required|string|max:255|unique:room_types,name',
            'description'  => 'nullable|string',
            'max_occupancy' => 'required|integer|min:0|max:255',
            'base_rate'    => 'required|numeric|min:100000',
            'hourly_rate'  => 'required|numeric|min:100000',
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dữ liệu không hợp lệ.',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $roomType = RoomType::create($validator->validated());
            return response()->json([
                'message' => 'Tạo loại phòng thành công.',
                'data'    => $roomType
            ], 201);
        } catch (QueryException $e) {
            Log::error('Lỗi tạo loại phòng: ' . $e->getMessage());
            return response()->json([
                'message' => 'Lỗi khi tạo loại phòng.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }


    public function show(RoomType $room_type)
    {
        $room_type->load('amenities');

        return response()->json([
            'message' => 'Thông tin loại phòng.',
            'data'    => $room_type,
        ]);
    }

    public function update(Request $request, RoomType $room_type)
{
    $messages = [
        'code.required' => 'Mã loại phòng là bắt buộc.',
        'code.string'   => 'Mã loại phòng phải là chuỗi ký tự.',
        'code.max'      => 'Mã loại phòng không được vượt quá 20 ký tự.',
        'code.unique'   => 'Mã loại phòng đã tồn tại.',

        'name.required' => 'Tên loại phòng là bắt buộc.',
        'name.string'   => 'Tên loại phòng phải là chuỗi ký tự.',
        'name.max'      => 'Tên loại phòng không được vượt quá 255 ký tự.',
        'name.unique'   => 'Tên loại phòng đã tồn tại.',

        'description.string' => 'Mô tả loại phòng phải là chuỗi ký tự.',

        'max_occupancy.required' => 'Sức chứa tối đa là bắt buộc.',
        'max_occupancy.integer'  => 'Sức chứa tối đa phải là số nguyên.',
        'max_occupancy.min'      => 'Sức chứa tối thiểu phải từ 0.',
        'max_occupancy.max'      => 'Sức chứa tối đa không vượt quá 255.',

        'base_rate.required' => 'Giá cơ bản là bắt buộc.',
        'base_rate.numeric'  => 'Giá cơ bản phải là số.',
        'base_rate.min'      => 'Giá cơ bản phải từ 100.000 VNĐ trở lên.',

        'hourly_rate.required' => 'Giá theo giờ là bắt buộc.',
        'hourly_rate.numeric'  => 'Giá theo giờ phải là số.',
        'hourly_rate.min'      => 'Giá theo giờ phải từ 100.000 VNĐ trở lên.',
    ];

    $validator = Validator::make($request->all(), [
        'code'          => [
            'required','string','max:20',
            Rule::unique('room_types','code')->ignore($room_type->id),
        ],
        'name'          => [
            'required','string','max:255',
            Rule::unique('room_types','name')->ignore($room_type->id),
        ],
        'description'   => 'nullable|string',
        'max_occupancy' => 'required|integer|min:0|max:255',
        'base_rate'     => 'required|numeric|min:100000',
        'hourly_rate'   => 'required|numeric|min:100000',
    ], $messages);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Dữ liệu không hợp lệ.',
            'errors'  => $validator->errors()
        ], 422);
    }

    try {
        $room_type->update($validator->validated());
        return response()->json([
            'message' => 'Cập nhật loại phòng thành công.',
            'data'    => $room_type->fresh()
        ]);
    } catch (QueryException $e) {
        Log::error('Lỗi cập nhật loại phòng: ' . $e->getMessage());
        return response()->json([
            'message' => 'Lỗi khi cập nhật loại phòng.',
            'error'   => $e->getMessage()
        ], 500);
    }
}

    public function destroy(RoomType $room_type)
    {
        try {
            $room_type->delete();
            return response()->json([
                'message' => 'Loại phòng đã được xóa.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi xóa loại phòng.',
                'error'   => $e->getMessage()
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
