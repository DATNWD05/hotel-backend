<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Promotion;
use App\Models\Room;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * Hiển thị danh sách tất cả các đơn đặt phòng.
     */
    public function index()
    {
        return Booking::with(['customer', 'rooms.roomType.amenities', 'creator', 'services', 'promotions'])->get();
    }

    /**
     * Hiển thị chi tiết một đơn đặt phòng cụ thể.
     */
    public function show($id)
    {
        $booking = Booking::with([
            'customer',
            'rooms.roomType.amenities',
            'creator',
            'services',
            'promotions',
        ])->findOrFail($id);

        return response()->json($booking);
    }

    /**
     * Tạo mới một đơn đặt phòng.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer.cccd'          => 'required|string|max:20',
            'customer.name'          => 'required|string|max:100',
            'customer.gender'        => 'nullable|in:male,female,other',
            'customer.email'         => 'required|email',
            'customer.phone'         => 'required|string|max:20',
            'customer.date_of_birth' => 'required|date',
            'customer.nationality'   => 'required|string|max:255',
            'customer.address'       => 'nullable|string',
            'customer.note'          => 'nullable|string',

            'room_ids'               => 'required|array|min:1',
            'room_ids.*'             => 'required|exists:rooms,id',

            'check_in_date'          => 'required|date|after_or_equal:today',
            'check_out_date'         => 'required|date|after:check_in_date',

            'services'               => 'nullable|array',
            'services.*.service_id'  => 'required|exists:services,id',
            'services.*.quantity'    => 'required|integer|min:1',

            'promotion_code'         => 'nullable|string|exists:promotions,code',
            'deposit_amount'         => 'nullable|numeric|min:0',
        ]);

        // Kiểm tra phòng đã bị đặt trùng lịch chưa
        foreach ($validated['room_ids'] as $roomId) {
            $conflict = DB::table('booking_room')
                ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
                ->where('booking_room.room_id', $roomId)
                ->whereIn('bookings.status', ['Pending', 'Confirmed'])
                ->where(function ($query) use ($validated) {
                    $query->whereBetween('bookings.check_in_date', [$validated['check_in_date'], $validated['check_out_date']])
                        ->orWhereBetween('bookings.check_out_date', [$validated['check_in_date'], $validated['check_out_date']])
                        ->orWhere(function ($q) use ($validated) {
                            $q->where('bookings.check_in_date', '<=', $validated['check_in_date'])
                                ->where('bookings.check_out_date', '>=', $validated['check_out_date']);
                        });
                })
                ->exists();

            if ($conflict) {
                return response()->json([
                    'message' => "Phòng ID {$roomId} đã được đặt trong thời gian này.",
                ], 422);
            }
        }

        // Tạo hoặc cập nhật khách hàng
        $customer = Customer::updateOrCreate(
            ['cccd' => $validated['customer']['cccd']],
            $validated['customer']
        );

        $user = Auth::user();
        $nights = Carbon::parse($validated['check_in_date'])->diffInDays($validated['check_out_date']);

        // Tính tiền phòng
        $roomTotal = 0;
        $roomsData = [];
        $rooms = Room::with('roomType')->whereIn('id', $validated['room_ids'])->get();

        foreach ($rooms as $room) {
            $rate = $room->roomType->base_rate ?? 0;
            $roomTotal += $rate * $nights;
            $roomsData[$room->id] = ['rate' => $rate];
        }

        // Tính tiền dịch vụ
        $serviceTotal = 0;
        $services = [];

        foreach ($validated['services'] ?? [] as $srv) {
            $service = Service::findOrFail($srv['service_id']);
            $subtotal = $service->price * $srv['quantity'];
            $serviceTotal += $subtotal;
            $services[$srv['service_id']] = ['quantity' => $srv['quantity']];
        }

        $rawTotal = $roomTotal + $serviceTotal;

        // Tính khuyến mãi nếu có
        $discount = 0;
        $promotion = null;

        if (!empty($validated['promotion_code'])) {
            $promotion = Promotion::where('code', $validated['promotion_code'])->first();
            if ($promotion && $promotion->isValid()) {
                $discount = $promotion->discount_type === 'percent'
                    ? $rawTotal * ($promotion->discount_value / 100)
                    : $promotion->discount_value;
            }
        }

        // Tạo đơn đặt phòng
        $booking = Booking::create([
            'customer_id'     => $customer->id,
            'created_by'      => $user->id,
            'check_in_date'   => $validated['check_in_date'],
            'check_out_date'  => $validated['check_out_date'],
            'status'          => 'Pending',
            'raw_total'       => $rawTotal,
            'discount_amount' => $discount,
            'total_amount'    => max(0, $rawTotal - $discount),
            'deposit_amount'  => $validated['deposit_amount'] ?? 0,
        ]);

        // Gắn phòng đã đặt
        if (!empty($roomsData)) {
            $booking->rooms()->attach($roomsData);
        }

        foreach ($booking->rooms as $room) {
            $room->update(['status' => 'booked']);
        }


        // Gắn dịch vụ
        if (!empty($services)) {
            $booking->services()->attach($services);
        }

        // Gắn khuyến mãi
        if ($promotion) {
            $booking->promotions()->attach($promotion->id, [
                'promotion_code' => $promotion->code,
                'applied_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Đặt phòng thành công',
            'data' => $booking->load(['customer', 'rooms.roomType', 'services', 'promotions']),
            'room_total' => $roomTotal,
            'service_total' => $serviceTotal,
        ]);
    }


    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'customer.cccd'         => 'required|string|max:20',
            'customer.name'         => 'required|string|max:100',
            'customer.gender'       => 'required|in:Male,Female,Other',
            'customer.email'        => 'required|email',
            'customer.phone'        => 'required|string|max:20',
            'customer.date_of_birth' => 'required|date',
            'customer.nationality'  => 'required|string|max:100',
            'customer.address'      => 'nullable|string',
            'customer.note'         => 'nullable|string',

            'room_ids'              => 'required|array|min:1',
            'room_ids.*'            => 'exists:rooms,id',
            'check_in_date'         => 'required|date|after_or_equal:today',
            'check_out_date'        => 'required|date|after:check_in_date',

            'services'              => 'nullable|array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity'   => 'required|integer|min:1',

            'promotion_code'        => 'nullable|string|exists:promotions,code',
            'deposit_amount'        => 'nullable|numeric|min:0',
        ]);

        $booking = Booking::with(['rooms.roomType', 'services', 'promotions'])->findOrFail($id);

        // Cập nhật hoặc lấy khách hàng
        $customer = Customer::updateOrCreate(
            ['cccd' => $validated['customer']['cccd']],
            $validated['customer']
        );

        $nights = Carbon::parse($validated['check_in_date'])->diffInDays($validated['check_out_date']);
        $roomTotal = 0;
        $roomsData = [];

        $rooms = Room::with('roomType')->whereIn('id', $validated['room_ids'])->get();

        foreach ($rooms as $room) {
            $rate = $room->roomType->base_rate ?? 0;
            $roomTotal += $rate * $nights;
            $roomsData[$room->id] = ['rate' => $rate];
        }

        // Dịch vụ
        $serviceTotal = 0;
        $services = [];
        foreach ($validated['services'] ?? [] as $srv) {
            $service = Service::findOrFail($srv['service_id']);
            $subtotal = $service->price * $srv['quantity'];
            $serviceTotal += $subtotal;
            $services[$srv['service_id']] = ['quantity' => $srv['quantity']];
        }

        $rawTotal = $roomTotal + $serviceTotal;

        // Khuyến mãi
        $discount = 0;
        $promotion = null;
        if (!empty($validated['promotion_code'])) {
            $promotion = Promotion::where('code', $validated['promotion_code'])->first();
            if ($promotion && $promotion->isValid()) {
                $discount = $promotion->discount_type === 'percent'
                    ? $rawTotal * ($promotion->discount_value / 100)
                    : $promotion->discount_value;
            }
        }

        // Cập nhật đơn
        $booking->update([
            'customer_id'     => $customer->id,
            'check_in_date'   => $validated['check_in_date'],
            'check_out_date'  => $validated['check_out_date'],
            'raw_total'       => $rawTotal,
            'discount_amount' => $discount,
            'total_amount'    => max(0, $rawTotal - $discount),
            'deposit_amount'  => $validated['deposit_amount'] ?? 0,
        ]);

        // Cập nhật các liên kết nhiều-nhiều
        $booking->rooms()->sync($roomsData);
        $booking->services()->sync($services);

        if ($promotion) {
            $booking->promotions()->sync([
                $promotion->id => [
                    'promotion_code' => $promotion->code,
                    'applied_at' => now()
                ]
            ]);
        } else {
            $booking->promotions()->detach(); // nếu bỏ khuyến mãi
        }

        return response()->json([
            'message' => 'Cập nhật đơn đặt phòng thành công',
            'data' => $booking->load(['customer', 'rooms.roomType', 'services', 'promotions']),
            'room_total' => $roomTotal,
            'service_total' => $serviceTotal,
        ]);
    }


    public function addServices(Request $request, $id)
    {
        $request->validate([
            'services' => 'required|array|min:1',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'required|integer|min:1',
        ]);

        $booking = Booking::with('services')->findOrFail($id);

        foreach ($request->services as $srv) {
            $serviceId = $srv['service_id'];
            $quantity = $srv['quantity'];

            // Nếu dịch vụ đã tồn tại → cộng thêm số lượng
            if ($booking->services->contains($serviceId)) {
                $currentQty = $booking->services->firstWhere('id', $serviceId)->pivot->quantity;
                $booking->services()->updateExistingPivot($serviceId, [
                    'quantity' => $currentQty + $quantity,
                ]);
            } else {
                $booking->services()->attach($serviceId, ['quantity' => $quantity]);
            }
        }

        $booking->load('services');

        // Cập nhật lại tổng tiền
        $booking->recalculateTotal();

        return response()->json([
            'message' => 'Thêm dịch vụ thành công',
            'data' => $booking->load('services')
        ]);
    }
}
