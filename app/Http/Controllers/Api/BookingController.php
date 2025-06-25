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

    public function show($id)
    {
        $booking = Booking::with(['customer', 'rooms.roomType.amenities', 'creator', 'services', 'promotions'])
            ->findOrFail($id);

        return response()->json($booking);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer.cccd'          => 'required|string|max:20',
            'customer.name'          => 'required|string|max:100',
            'customer.gender'        => 'nullable|in:male,female,other',
            'customer.email'         => 'required|email',
            'customer.phone'         => 'required|string|max:20',
            'customer.date_of_birth' => [
                'required',
                'date',
                'before_or_equal:' . now()->subYears(18)->toDateString(),
            ],
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
        ], [
            'customer.date_of_birth.before_or_equal' => 'Khách hàng phải đủ 18 tuổi mới được đặt phòng.',
        ]);

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

        $customer = Customer::updateOrCreate(
            ['cccd' => $validated['customer']['cccd']],
            $validated['customer']
        );

        $user = Auth::user();
        $nights = Carbon::parse($validated['check_in_date'])->diffInDays($validated['check_out_date']);

        $roomTotal = 0;
        $roomsData = Room::with('roomType')->findMany($validated['room_ids'])->mapWithKeys(function ($room) use (&$roomTotal, $nights) {
            $rate = $room->roomType->base_rate ?? 0;
            $roomTotal += $rate * $nights;
            return [$room->id => ['rate' => $rate]];
        });

        $serviceTotal = 0;
        $servicesData = collect($validated['services'] ?? [])->mapWithKeys(function ($srv) use (&$serviceTotal) {
            $service = Service::findOrFail($srv['service_id']);
            $subtotal = $service->price * $srv['quantity'];
            $serviceTotal += $subtotal;
            return [$srv['service_id'] => ['quantity' => $srv['quantity']]];
        });

        $rawTotal = $roomTotal + $serviceTotal;

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

        $booking->rooms()->attach($roomsData);
        $booking->services()->attach($servicesData);

        foreach ($booking->rooms as $room) {
            $room->update(['status' => 'booked']);
        }

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
            'customer.date_of_birth' => [
                'required',
                'date',
                'before_or_equal:' . now()->subYears(18)->toDateString()
            ],
            'customer.nationality'  => 'required|string|max:100',
            'customer.address'      => 'nullable|string',
            'customer.note'         => 'nullable|string',

            'room_ids'              => 'required|array|min:1',
            'room_ids.*'            => 'exists:rooms,id',
            'check_in_date'         => 'required|date|after_or_equal:today',
            'check_out_date'        => 'required|date|after:check_in_date',

            'check_in_at'           => 'nullable|date',
            'check_out_at'          => 'nullable|date|after_or_equal:check_in_at',

            'services'              => 'nullable|array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity'   => 'required|integer|min:1',

            'promotion_code'        => 'nullable|string|exists:promotions,code',
            'deposit_amount'        => 'nullable|numeric|min:0',
        ]);

        $booking = Booking::with(['rooms.roomType', 'services', 'promotions'])->findOrFail($id);

        // Cập nhật hoặc tạo khách hàng
        $customer = Customer::updateOrCreate(
            ['cccd' => $validated['customer']['cccd']],
            $validated['customer']
        );

        // Kiểm tra có thay đổi dữ liệu cần tính lại tiền không
        $shouldRecalculate = (
            $booking->check_in_date !== $validated['check_in_date'] ||
            $booking->check_out_date !== $validated['check_out_date'] ||
            collect($validated['room_ids'])->sort()->values()->toJson() !== $booking->rooms->pluck('id')->sort()->values()->toJson() ||
            collect($validated['services'] ?? [])->toJson() !== $booking->services->map(fn($s) => [
                'service_id' => $s->id,
                'quantity' => $s->pivot->quantity
            ])->values()->toJson() ||
            ($validated['promotion_code'] ?? null) !== optional($booking->promotions->first())->code
        );

        $roomTotal = $serviceTotal = $discount = 0;
        $roomsData = $services = [];
        $promotion = null;

        if ($shouldRecalculate) {
            $nights = Carbon::parse($validated['check_in_date'])->diffInDays($validated['check_out_date']);
            $rooms = Room::with('roomType')->whereIn('id', $validated['room_ids'])->get();

            foreach ($rooms as $room) {
                $rate = $room->roomType->base_rate ?? 0;
                $roomTotal += $rate * $nights;
                $roomsData[$room->id] = ['rate' => $rate];
            }

            foreach ($validated['services'] ?? [] as $srv) {
                $service = Service::findOrFail($srv['service_id']);
                $subtotal = $service->price * $srv['quantity'];
                $serviceTotal += $subtotal;
                $services[$srv['service_id']] = ['quantity' => $srv['quantity']];
            }

            $rawTotal = $roomTotal + $serviceTotal;

            if (!empty($validated['promotion_code'])) {
                $promotion = Promotion::where('code', $validated['promotion_code'])->first();
                if ($promotion && $promotion->isValid()) {
                    $discount = $promotion->discount_type === 'percent'
                        ? $rawTotal * ($promotion->discount_value / 100)
                        : $promotion->discount_value;
                }
            }

            $booking->raw_total = $rawTotal;
            $booking->discount_amount = $discount;
            $booking->total_amount = max(0, $rawTotal - $discount);

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
                $booking->promotions()->detach();
            }
        }

        // Trạng thái và thời gian check-in/out
        $status = $booking->status;
        if (!empty($validated['check_in_at'])) {
            $booking->check_in_at = $validated['check_in_at'];
            $status = 'Checked-in';
        }

        if (!empty($validated['check_out_at'])) {
            $booking->check_out_at = $validated['check_out_at'];
            $status = 'Checked-out';
        }

        // Cập nhật booking cuối cùng
        $booking->fill([
            'customer_id'    => $customer->id,
            'check_in_date'  => $validated['check_in_date'],
            'check_out_date' => $validated['check_out_date'],
            'deposit_amount' => $validated['deposit_amount'] ?? 0,
            'status'         => $status,
        ])->save();

        return response()->json([
            'message' => 'Cập nhật đơn đặt phòng thành công',
            'data' => $booking->fresh()->load(['customer', 'rooms.roomType', 'services', 'promotions']),
        ]);
    }



    public function addServices(Request $request, $id)
    {
        $request->validate([
            'services' => 'required|array|min:1',
            'services.*.room_id' => 'required|exists:rooms,id',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'required|integer|min:1',
        ]);

        $booking = Booking::with(['services', 'rooms'])->findOrFail($id);

        foreach ($request->services as $srv) {
            $roomId = $srv['room_id'];
            $serviceId = $srv['service_id'];
            $quantity = $srv['quantity'];

            // Kiểm tra phòng có thuộc booking không
            if (!$booking->rooms->contains('id', $roomId)) {
                return response()->json([
                    'message' => "Phòng ID {$roomId} không thuộc booking này."
                ], 422);
            }

            // Kiểm tra xem đã tồn tại chưa
            $existing = DB::table('booking_service')
                ->where('booking_id', $booking->id)
                ->where('service_id', $serviceId)
                ->where('room_id', $roomId)
                ->first();

            if ($existing) {
                DB::table('booking_service')
                    ->where('id', $existing->id)
                    ->update([
                        'quantity' => $existing->quantity + $quantity,
                        'updated_at' => now()
                    ]);
            } else {
                DB::table('booking_service')->insert([
                    'booking_id' => $booking->id,
                    'room_id' => $roomId,
                    'service_id' => $serviceId,
                    'quantity' => $quantity,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        // Reload để tính lại tổng tiền
        $booking->load(['services', 'rooms.roomType']);
        $booking->recalculateTotal();

        return response()->json([
            'message' => 'Thêm dịch vụ theo phòng thành công',
            'data' => $booking->load(['services'])
        ]);
    }
}
