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

    // thông tin khi checkin
    public function showCheckInInfo($id)
    {
        $booking = Booking::with([
            'customer',
            'creator',
            'rooms.roomType.amenities',
            'services',
            'promotions'
        ])->find($id);

        if (!$booking) {
            return response()->json(['error' => 'Không tìm thấy booking'], 404);
        }

        return response()->json([
            'booking_id' => $booking->id,
            'status' => $booking->status,
            'check_in_date' => $booking->check_in_date,
            'check_out_date' => $booking->check_out_date,
            'deposit_amount' => $booking->deposit_amount,
            'total_amount' => $booking->total_amount,
            'raw_total' => $booking->raw_total,
            'discount_amount' => $booking->discount_amount,
            'customer' => [
                'name' => $booking->customer->name,
                'gender' => $booking->customer->gender,
                'email' => $booking->customer->email,
                'phone' => $booking->customer->phone,
                'cccd' => $booking->customer->cccd,
                'nationality' => $booking->customer->nationality,
                'address' => $booking->customer->address,
            ],
            'rooms' => $booking->rooms->map(function ($room) {
                return [
                    'room_number' => $room->room_number,
                    'status' => $room->status,
                    'image' => $room->image,
                    'rate' => $room->pivot->rate,
                    'type' => [
                        'name' => $room->roomType->name ?? null,
                        'max_occupancy' => $room->roomType->max_occupancy ?? null,
                        'amenities' => $room->roomType->amenities->map(function ($amenity) {
                            return [
                                'name' => $amenity->name,
                                'icon' => $amenity->icon,
                                'quantity' => $amenity->pivot->quantity ?? 1
                            ];
                        })
                    ]
                ];
            }),
            'services' => $booking->services->map(function ($service) {
                return [
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => $service->price,
                    'quantity' => $service->pivot->quantity
                ];
            }),
            'created_by' => $booking->creator->name ?? null
        ]);
    }

    /**
     * API thực hiện hành động check-in
     * POST /api/check-in/{id}
     */
    public function checkIn($id, Request $request)
    {
        $booking = Booking::with('rooms')->find($id);

        if (!$booking) {
            return response()->json(['error' => 'Không tìm thấy booking'], 404);
        }

        if (!in_array($booking->status, ['Pending', 'Booked'])) {
            return response()->json([
                'error' => 'Booking hiện không ở trạng thái cho phép check-in'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $booking->status = 'Checked-in';
            $booking->check_in_at = now();
            $booking->save();

            foreach ($booking->rooms as $room) {
                $room->update(['status' => 'booked']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Check-in thành công',
                'booking_id' => $booking->id,
                'check_in_at' => $booking->check_in_at,
                'rooms' => $booking->rooms->map(fn($room) => [
                    'room_number' => $room->room_number,
                    'status' => $room->status
                ])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Đã xảy ra lỗi khi check-in',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function checkOut($id)
    {
        $booking = Booking::with(['rooms.roomType', 'services'])->find($id);

        if (!$booking) {
            return response()->json(['error' => 'Không tìm thấy đơn đặt phòng'], 404);
        }

        $checkIn = Carbon::parse($booking->check_in_date);
        $checkOut = Carbon::parse($booking->check_out_date);

        // Nếu ngày check-out nằm trước check-in, trả lỗi
        if ($checkOut->isBefore($checkIn)) {
            return response()->json([
                'error' => 'Ngày check-out không hợp lệ (trước ngày check-in)'
            ], 400);
        }

        $nights = $checkIn->diffInDays($checkOut);



        // Tính tiền phòng
        $roomTotal = 0;
        $roomDetails = [];

        foreach ($booking->rooms as $room) {
            $rate = floatval(optional($room->roomType)->base_rate ?? 0);
            $total = $rate * $nights;

            $roomTotal += $total;

            $roomDetails[] = [
                'room_number' => $room->room_number,
                'base_rate' => $rate,
                'total' => $total
            ];
        }

        // Tính tiền dịch vụ
        $serviceTotal = 0;
        foreach ($booking->services as $service) {
            $quantity = intval($service->pivot->quantity ?? 1);
            $serviceTotal += floatval($service->price) * $quantity;
        }

        // Tính giảm giá
        $discountPercent = floatval($booking->discount_amount ?? 0);
        $rawTotal = $roomTotal + $serviceTotal;
        $discount = ($discountPercent > 0) ? ($rawTotal * $discountPercent / 100) : 0;

        // Tổng tiền
        $totalAmount = $rawTotal - $discount;

        // Cập nhật Booking
        $booking->status = 'Checked-out';
        $booking->total_amount = $totalAmount;
        $booking->save();

        return response()->json([
            'message' => 'Check-out thành công!',
            'booking_id' => $booking->id,
            'room_details' => $roomDetails,
            'room_total' => $roomTotal,
            'service_total' => $serviceTotal,
            'discount_amount' => $discount,
            'raw_total' => $rawTotal,
            'total_amount' => $totalAmount,
            'nights' => $nights,
            'status' => $booking->status,
        ]);
    }

    public function cancel($id)
    {
        $booking = Booking::findOrFail($id);

        // Nếu đã check-out hoặc đang ở thì không cho huỷ
        if (in_array($booking->status, ['Checked-out', 'Checked-in'])) {
            return response()->json([
                'error' => 'Không thể huỷ đơn đã nhận phòng hoặc đã trả phòng!'
            ], 400);
        }

        $booking->status = 'Canceled';
        $booking->save();

        return response()->json([
            'message' => 'Huỷ đơn đặt phòng thành công!',
            'booking_id' => $booking->id,
            'status' => $booking->status
        ]);
    }
}
