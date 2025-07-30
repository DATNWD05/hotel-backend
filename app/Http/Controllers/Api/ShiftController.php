<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class ShiftController extends Controller
{
    // use AuthorizesRequests;

    // public function __construct()
    // {
    //     $this->authorizeResource(Shift::class, 'shifts');
    // }

    public function index()
    {
        $shifts = Shift::all();
        return response()->json($shifts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'name' => 'required|string|max:255',
                'start_time' => 'required|date_format:H:i:s',
                'end_time' => 'required|date_format:H:i:s',
                'hourly_rate' => 'required|numeric|min:0',
            ],
            [
                'name.required' => 'Tên ca làm là bắt buộc.',
                'name.string' => 'Tên ca làm phải là chuỗi.',
                'start_time.required' => 'Giờ bắt đầu là bắt buộc.',
                'start_time.date_format' => 'Giờ bắt đầu phải có định dạng HH:MM:SS.',
                'end_time.required' => 'Giờ kết thúc là bắt buộc.',
                'end_time.date_format' => 'Giờ kết thúc phải có định dạng HH:MM:SS.',
                'hourly_rate.required' => 'Mức lương theo giờ là bắt buộc.',
                'hourly_rate.numeric' => 'Mức lương phải là số.',
                'hourly_rate.min' => 'Mức lương phải lớn hơn hoặc bằng 0.',
            ]
        );

        // Kiểm tra giờ kết thúc phải sau giờ bắt đầu
        if ($request->start_time >= $request->end_time) {
            return response()->json([
                'message' => 'Giờ kết thúc phải sau giờ bắt đầu.'
            ], 422);
        }

        $shift = Shift::create($validated);

        return response()->json([
            'message' => 'Tạo ca làm thành công',
            'data' => $shift
        ], 201);
    }

    public function show(Shift $shift)
    {
        return response()->json($shift);
    }

    public function update(Request $request, Shift $shift)
    {
        $validated = $request->validate(
            [
                'name' => 'sometimes|required|string|max:255',
                'start_time' => 'sometimes|required|date_format:H:i:s',
                'end_time' => 'sometimes|required|date_format:H:i:s',
                'hourly_rate' => 'sometimes|required|numeric|min:0',
            ],
            [
                'name.required' => 'Tên ca làm là bắt buộc.',
                'start_time.required' => 'Giờ bắt đầu là bắt buộc.',
                'start_time.date_format' => 'Giờ bắt đầu phải có định dạng HH:MM:SS.',
                'end_time.required' => 'Giờ kết thúc là bắt buộc.',
                'end_time.date_format' => 'Giờ kết thúc phải có định dạng HH:MM:SS.',
                'hourly_rate.numeric' => 'Mức lương phải là số.',
                'hourly_rate.min' => 'Mức lương phải lớn hơn hoặc bằng 0.',
            ]
        );

        // Nếu có cả start_time và end_time mới kiểm tra logic
        if (isset($validated['start_time'], $validated['end_time']) && $validated['start_time'] >= $validated['end_time']) {
            return response()->json([
                'message' => 'Giờ kết thúc phải sau giờ bắt đầu.'
            ], 422);
        }

        $shift->update($validated);

        return response()->json([
            'message' => 'Cập nhật ca làm thành công',
            'data' => $shift
        ]);
    }

    public function destroy(Shift $shift)
    {
        // Kiểm tra nếu ca làm có liên kết với chấm công
        if ($shift->attendances()->exists()) {
            return response()->json([
                'message' => 'Không thể xóa ca làm vì đã có nhân viên được chấm công trong ca này.'
            ], 409);
        }

        $shift->delete();

        return response()->json([
            'message' => 'Xóa ca làm thành công'
        ]);
    }
}
