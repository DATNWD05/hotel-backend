<?php
// app/Http/Controllers/RoomPriceController.php

namespace App\Http\Controllers\Api;

use App\Models\RoomPrice;
use Illuminate\Http\Request;

class RoomPriceController extends Controller
{
    // Lấy tất cả giá phòng
    public function index()
    {
        $roomPrices = RoomPrice::all();
        return response()->json($roomPrices);
    }

    // Thêm mới giá phòng
    public function store(Request $request)
    {
        $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'name' => 'required|string',
            'price' => 'required|numeric',
            'currency' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'default' => 'nullable|boolean',
        ]);

        $roomPrice = RoomPrice::create($request->all());

        return response()->json($roomPrice, 201);
    }

    // Cập nhật giá phòng
    public function update(Request $request, $id)
    {
        $roomPrice = RoomPrice::findOrFail($id);

        $request->validate([
            'price' => 'required|numeric',
            'currency' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'default' => 'nullable|boolean',
        ]);

        $roomPrice->update($request->all());

        return response()->json($roomPrice);
    }

    // Xóa giá phòng
    public function destroy($id)
    {
        $roomPrice = RoomPrice::findOrFail($id);
        $roomPrice->delete();
        
        return response()->json(['message' => 'Room price deleted successfully']);
    }
}
