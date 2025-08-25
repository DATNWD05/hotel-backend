<?php

namespace App\Http\Controllers\Api;

use Carbon\Carbon;
use App\Models\Room;
use App\Http\Controllers\Controller;
use App\Mail\DepositLinkMail;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\Customer;
use App\Models\Promotion;
use App\Models\BookingRoomAmenity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class BookingController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(Booking::class, 'booking');
    }
    /**
     * Hiển thị danh sách tất cả các đơn đặt phòng.
     */
    public function index()
    {
        return Booking::with([
            'customer',
            'rooms.roomType.amenities',
            'creator',
            'services',
            'promotions'
        ])
            ->orderByDesc('created_at')
            ->get();
    }


    public function show(Booking $booking)
    {
        $booking->load([
            'customer',
            'rooms.roomType.amenities',
            'creator',
            'services',
            'promotions',
        ]);

        $servicesByRoom = DB::table('booking_service')
            ->join('services', 'booking_service.service_id', '=', 'services.id')
            ->select(
                'booking_service.room_id',
                'services.id as service_id',
                'services.name',
                'services.price',
                'booking_service.quantity'
            )
            ->where('booking_id', $booking->id)
            ->get()
            ->groupBy('room_id');

        $booking->rooms->transform(function ($room) use ($servicesByRoom) {
            $room->services = $servicesByRoom[$room->id] ?? collect();
            return $room;
        });

        return response()->json(['booking' => $booking]);
    }

    public function getAvailableRooms(Request $request)
    {
        $validated = $request->validate([
            'check_in_date'  => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'room_type_id'   => 'nullable|exists:room_types,id',
            'is_hourly'      => 'nullable|boolean',
        ]);

        $isHourly = (bool)($validated['is_hourly'] ?? false);

        $checkIn  = Carbon::parse($validated['check_in_date']);
        $checkOut = Carbon::parse($validated['check_out_date']);


        $query = Room::query();
        if (!empty($validated['room_type_id'])) {
            $query->where('room_type_id', $validated['room_type_id']);
        }

        $rooms = $query->get()->filter(function ($room) use ($checkIn, $checkOut, $isHourly) {
            $rate = $isHourly
                ? ($room->roomType->hourly_rate ?? 0)
                : ($room->roomType->base_rate ?? 0);

            if ($rate <= 0) return false;

            $hasConflict = DB::table('booking_room')
                ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
                ->where('booking_room.room_id', $room->id)
                ->whereIn('bookings.status', ['Pending', 'Confirmed', 'Checked-in'])
                ->where(function ($q) use ($checkIn, $checkOut) {
                    $q->where('bookings.check_in_date', '<', $checkOut)
                        ->where('bookings.check_out_date', '>', $checkIn);
                })
                ->exists();

            return !$hasConflict;
        });

        return response()->json([
            'data' => $rooms->values()->map(function ($room) use ($isHourly) {
                return [
                    'id'           => $room->id,
                    'room_number'  => $room->room_number,
                    'room_type_id' => $room->room_type_id,
                    'available'    => true,
                    'room_type'    => [
                        'id'         => $room->roomType->id,
                        'name'       => $room->roomType->name,
                        'base_rate'  => $isHourly ? ($room->roomType->hourly_rate ?? 0) : ($room->roomType->base_rate ?? 0),
                    ],
                ];
            }),
        ]);
    }

    /**
     * Validate room availability before booking.
     */
    public function validateRooms(Request $request)
    {
        $validated = $request->validate([
            'room_ids' => 'required|array|min:1',
            'room_ids.*' => 'required|exists:rooms,id',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'is_hourly' => 'nullable|boolean',
        ]);

        $checkIn = Carbon::parse($validated['check_in_date']);
        $checkOut = Carbon::parse($validated['check_out_date']);
        $roomIds = $validated['room_ids'];

        $unavailableRooms = [];

        foreach ($roomIds as $roomId) {
            $conflict = DB::table('booking_room')
                ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
                ->where('booking_room.room_id', $roomId)
                ->whereIn('bookings.status', ['Pending', 'Confirmed', 'Checked-in'])
                ->where(function ($query) use ($checkIn, $checkOut) {
                    $query->where(function ($q) use ($checkIn, $checkOut) {
                        $q->where('bookings.check_in_date', '<', $checkOut)
                            ->where('bookings.check_out_date', '>', $checkIn);
                    });
                })
                ->exists();

            if ($conflict) {
                $room = Room::find($roomId);
                $unavailableRooms[] = [
                    'id' => $room->id,
                    'room_number' => $room->room_number,
                ];
            }
        }

        if (!empty($unavailableRooms)) {
            return response()->json([
                'status' => 'invalid',
                'unavailable_rooms' => $unavailableRooms,
            ], 422);
        }

        return response()->json([
            'status' => 'valid',
            'unavailable_rooms' => [],
        ]);
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
            'customer.cccd_image'    => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // ảnh tối đa 5MB

            'room_ids'               => 'required|array|min:1',
            'room_ids.*'             => 'required|exists:rooms,id',

            'check_in_date'          => 'required|date',
            'check_out_date'         => 'required|date|after:check_in_date',

            'is_hourly'              => 'nullable|boolean',

            'services'               => 'nullable|array',
            'services.*.service_id'  => 'required|exists:services,id',
            'services.*.quantity'    => 'required|integer|min:1',
            'services.*.room_id'     => 'nullable|exists:rooms,id',

            'promotion_code'         => 'nullable|string|exists:promotions,code',
            'deposit_amount'         => 'nullable|numeric|min:0',
        ], [
            'customer.date_of_birth.before_or_equal' => 'Khách hàng phải đủ 18 tuổi mới được đặt phòng.',
        ]);

        $isHourly = $validated['is_hourly'] ?? false;

        $checkIn = Carbon::parse($validated['check_in_date']);
        $checkOut = Carbon::parse($validated['check_out_date']);

        if ($isHourly) {
            // Kiểm tra giờ đặt phòng
            if ($checkIn->hour >= 22) {
                return response()->json([
                    'message' => 'Không thể đặt phòng theo giờ sau 22h. Vui lòng chọn đặt phòng qua đêm (theo ngày).',
                ], 422);
            }
        } else {
            if ($checkIn->copy()->startOfDay()->equalTo($checkOut->copy()->startOfDay())) {
                return response()->json([
                    'message' => 'Với đặt phòng theo ngày, bạn phải lưu trú ít nhất 1 đêm (qua ngày hôm sau).',
                ], 422);
            }
            if (!($checkOut->hour === 12 && $checkOut->minute === 0)) {
                return response()->json([
                    'message' => 'Với đặt phòng qua đêm, thời gian check-out phải là 12:00 trưa hôm sau.',
                ], 422);
            }
        }


        foreach ($validated['room_ids'] as $roomId) {
            $conflict = DB::table('booking_room')
                ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
                ->where('booking_room.room_id', $roomId)
                ->whereIn('bookings.status', ['Pending', 'Confirmed'])
                ->where(function ($query) use ($checkIn, $checkOut) {
                    $query->where(function ($q) use ($checkIn, $checkOut) {
                        $q->where('bookings.check_in_date', '<', $checkOut)
                            ->where('bookings.check_out_date', '>', $checkIn);
                    });
                })
                ->exists();

            if ($conflict) {
                return response()->json([
                    'message' => "Phòng ID {$roomId} đã được đặt trong khoảng thời gian này.",
                ], 422);
            }
        }

        // Xử lý lưu ảnh CCCD
        if ($request->hasFile('customer.cccd_image')) {
            $path = $request->file('customer.cccd_image')->store('cccd_images', 'public');
            $validated['customer']['cccd_image_path'] = $path;
        }

        $customer = Customer::updateOrCreate(
            ['cccd' => $validated['customer']['cccd']],
            $validated['customer']
        );

        $user = Auth::user();
        $start = Carbon::parse($validated['check_in_date']);
        $end   = Carbon::parse($validated['check_out_date']);
        $duration = $isHourly ? max(1, ceil($start->diffInMinutes($end) / 60)) : max(1, $start->diffInDays($end));

        $roomTotal = 0;
        $roomsData = Room::with('roomType')->findMany($validated['room_ids'])->mapWithKeys(function ($room) use (&$roomTotal, $duration, $isHourly) {
            $rate = $isHourly
                ? ($room->roomType->hourly_rate ?? 0)
                : ($room->roomType->base_rate ?? 0);
            $roomTotal += $rate * $duration;
            return [$room->id => ['rate' => $rate]];
        });

        $serviceTotal = 0;
        $servicesData = collect($validated['services'] ?? [])->map(function ($srv) use (&$serviceTotal) {
            $service = Service::findOrFail($srv['service_id']);
            $subtotal = $service->price * $srv['quantity'];
            $serviceTotal += $subtotal;
            return [
                'service_id' => $srv['service_id'],
                'quantity'   => $srv['quantity'],
                'room_id'    => $srv['room_id'] ?? null
            ];
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
                $promotion->increment('used_count');
            }
        }

        $status = (empty($validated['deposit_amount']) || $validated['deposit_amount'] == 0)
            ? 'Confirmed'
            : 'Pending';

        $booking = Booking::create([
            'customer_id'     => $customer->id,
            'created_by'      => $user->id,
            'check_in_date'   => $validated['check_in_date'],
            'check_out_date'  => $validated['check_out_date'],
            'status'          => $status,
            'raw_total'       => $rawTotal,
            'discount_amount' => $discount,
            'total_amount'    => null,
            'deposit_amount'  => $validated['deposit_amount'] ?? 0,
            'is_hourly'       => $isHourly,
        ]);

        $booking->rooms()->attach($roomsData);

        foreach ($servicesData as $service) {
            $booking->services()->attach($service['service_id'], [
                'quantity' => $service['quantity'],
                'room_id'  => $service['room_id'],
            ]);
        }

        foreach ($booking->rooms as $room) {
            $room->update(['status' => 'booked']);
        }

        if ($promotion) {
            $booking->promotions()->attach($promotion->id, [
                'promotion_code' => $promotion->code,
                'applied_at' => now(),
            ]);
        }

        if (!empty($validated['deposit_amount']) && $validated['deposit_amount'] > 0) {
            $depositUrl = route('deposit.vnpay.create', ['booking_id' => $booking->id]);
            Mail::to($customer->email)->send(new DepositLinkMail($booking, $depositUrl));
        }

        return response()->json([
            'message'        => 'Đặt phòng thành công',
            'data'           => $booking->load(['customer', 'rooms.roomType', 'services', 'promotions']),
            'room_total'     => $roomTotal,
            'service_total'  => $serviceTotal,
        ]);
    }


    public function update(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'customer.cccd'          => 'sometimes|string|max:20',
            'customer.name'          => 'sometimes|string|max:100',
            'customer.gender'        => 'sometimes|in:male,female,other',
            'customer.email'         => 'sometimes|email',
            'customer.phone'         => 'sometimes|string|max:20',
            'customer.date_of_birth' => ['sometimes', 'date', 'before_or_equal:' . now()->subYears(18)->toDateString()],
            'customer.nationality'   => 'sometimes|string|max:100',
            'customer.address'       => 'nullable|string',
            'customer.note'          => 'nullable|string',
            'customer.cccd_image'    => 'nullable|image|mimes:jpeg,png,jpg|max:5120',

            'room_ids'               => 'sometimes|array|min:1',
            'room_ids.*'             => 'exists:rooms,id',

            'check_in_date'          => 'sometimes|date',
            'check_out_date'         => 'sometimes|date|after:check_in_date',

            'check_in_at'            => 'nullable|date',
            'check_out_at'           => 'nullable|date|after_or_equal:check_in_at',

            'services'               => 'nullable|array',
            'services.*.service_id'  => 'required_with:services|exists:services,id',
            'services.*.quantity'    => 'required_with:services|integer|min:1',
            'services.*.room_id'     => 'nullable|exists:rooms,id',

            'promotion_code'         => 'nullable|string|exists:promotions,code',
            'deposit_amount'         => 'nullable|numeric|min:0',

            'is_hourly'              => 'nullable|boolean',
        ]);

        $isHourly = $validated['is_hourly'] ?? $booking->is_hourly ?? false;

        $newCheckIn  = $validated['check_in_date'] ?? $booking->check_in_date;
        $newCheckOut = $validated['check_out_date'] ?? $booking->check_out_date;

        $checkIn  = Carbon::parse($newCheckIn);
        $checkOut = Carbon::parse($newCheckOut);

        // Kiểm tra quy tắc đặt phòng giống store
        if ($isHourly) {
            if ($checkIn->copy()->startOfDay()->equalTo($checkOut->copy()->startOfDay())) {
                return response()->json([
                    'message' => 'Với đặt phòng theo ngày, bạn phải lưu trú ít nhất 1 đêm.',
                ], 422);
            }
        }

        // Kiểm tra trùng phòng nếu đổi phòng hoặc ngày/giờ
        if ($request->hasAny(['room_ids', 'check_in_date', 'check_out_date', 'is_hourly'])) {
            $newRoomIds = $validated['room_ids'] ?? $booking->rooms->pluck('id')->toArray();
            foreach ($newRoomIds as $roomId) {
                $conflict = DB::table('booking_room')
                    ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
                    ->where('booking_room.room_id', $roomId)
                    ->where('bookings.id', '!=', $booking->id)
                    ->whereIn('bookings.status', ['Pending', 'Confirmed'])
                    ->where(function ($query) use ($checkIn, $checkOut) {
                        $query->where('check_in_date', '<', $checkOut)
                            ->where('check_out_date', '>', $checkIn);
                    })
                    ->exists();

                if ($conflict) {
                    return response()->json([
                        'message' => "Phòng ID {$roomId} đã được đặt trong thời gian bạn chọn.",
                    ], 422);
                }
            }
        }

        // Cập nhật khách hàng
        if ($request->has('customer')) {
            $customerData = $validated['customer'];

            // Xử lý upload ảnh CCCD nếu có
            if ($request->hasFile('customer.cccd_image')) {
                if (!empty($booking->customer->cccd_image_path)) {
                    Storage::disk('public')->delete($booking->customer->cccd_image_path);
                }
                $customerData['cccd_image_path'] = $request->file('customer.cccd_image')->store('cccd_images', 'public');
            }

            $customer = Customer::updateOrCreate(
                ['cccd' => $customerData['cccd'] ?? $booking->customer->cccd],
                $customerData
            );
            $booking->customer_id = $customer->id;
        } else {
            $customer = $booking->customer;
        }

        // Cập nhật thông tin booking cơ bản
        $booking->fill([
            'check_in_date'  => $newCheckIn,
            'check_out_date' => $newCheckOut,
            'check_in_at'    => $validated['check_in_at'] ?? $booking->check_in_at,
            'check_out_at'   => $validated['check_out_at'] ?? $booking->check_out_at,
            'is_hourly'      => $isHourly,
        ]);

        // Tính thời gian lưu trú
        $duration = $isHourly ? max(1, ceil($checkIn->diffInMinutes($checkOut) / 60)) : max(1, $checkIn->diffInDays($checkOut));

        // Cập nhật phòng
        $roomTotal = 0;
        if ($request->has('room_ids')) {
            $oldRoomIds = $booking->rooms->pluck('id')->toArray();
            Room::whereIn('id', $oldRoomIds)->update(['status' => 'available']);

            $rooms = Room::with('roomType')->findMany($validated['room_ids']);
            $roomsData = $rooms->mapWithKeys(function ($room) use (&$roomTotal, $duration, $isHourly) {
                $rate = $isHourly ? ($room->roomType->hourly_rate ?? 0) : ($room->roomType->base_rate ?? 0);
                $roomTotal += $rate * $duration;
                return [$room->id => ['rate' => $rate]];
            });
            $booking->rooms()->sync($roomsData);

            Room::whereIn('id', $validated['room_ids'])->update(['status' => 'booked']);
        } else {
            foreach ($booking->rooms as $room) {
                $rate = $isHourly ? ($room->roomType->hourly_rate ?? 0) : ($room->roomType->base_rate ?? 0);
                $roomTotal += $rate * $duration;
            }
        }

        // Cập nhật dịch vụ
        $serviceTotal = 0;
        if ($request->has('services')) {
            $booking->services()->detach();
            foreach ($validated['services'] as $srv) {
                $service = Service::findOrFail($srv['service_id']);
                $subtotal = $service->price * $srv['quantity'];
                $serviceTotal += $subtotal;
                $booking->services()->attach($srv['service_id'], [
                    'quantity' => $srv['quantity'],
                    'room_id'  => $srv['room_id'] ?? null,
                ]);
            }
        } else {
            foreach ($booking->services as $srv) {
                $serviceTotal += $srv->price * $srv->pivot->quantity;
            }
        }

        // Tính lại tổng
        $rawTotal = $roomTotal + $serviceTotal;

        // Khuyến mãi
        $discount = 0;
        if ($request->has('promotion_code')) {
            $promotion = Promotion::where('code', $validated['promotion_code'])->first();
            if ($promotion && $promotion->isValid()) {
                $discount = $promotion->discount_type === 'percent'
                    ? $rawTotal * ($promotion->discount_value / 100)
                    : $promotion->discount_value;
                $promotion->increment('used_count');
                $booking->promotions()->sync([$promotion->id => [
                    'promotion_code' => $promotion->code,
                    'applied_at' => now(),
                ]]);
            }
        }

        // Cập nhật trạng thái
        if (!empty($validated['check_in_at'])) {
            $booking->status = 'Checked-in';
        } elseif ($request->has('deposit_amount') && $validated['deposit_amount'] > 0) {
            $booking->status = 'Pending';
        } else {
            $booking->status = 'Confirmed';
        }
        if (!empty($validated['check_out_at'])) {
            $booking->status = 'Checked-out';
        }

        // Gửi email nếu thay đổi cọc
        if ($request->has('deposit_amount')) {
            if ($booking->deposit_amount != $validated['deposit_amount'] && $validated['deposit_amount'] > 0) {
                $depositUrl = route('deposit.vnpay.create', ['booking_id' => $booking->id]);
                Mail::to($customer->email)->send(new DepositLinkMail($booking, $depositUrl));
            }
            $booking->deposit_amount = $validated['deposit_amount'];
        }

        // Lưu lại
        $booking->raw_total       = $rawTotal;
        $booking->discount_amount = $discount;
        $booking->total_amount    = null;
        $booking->save();

        return response()->json([
            'message' => 'Cập nhật đơn đặt phòng thành công',
            'data'    => $booking->fresh()->load(['customer', 'rooms.roomType', 'services', 'promotions']),
        ]);
    }


    public function addServices(Request $request, Booking $booking)
    {
        // Không cho thêm nếu đã checkout
        if ($booking->status === 'Checked-out') {
            return response()->json([
                'message' => 'Không thể thêm dịch vụ vì đơn đặt phòng đã được checkout.'
            ], 422);
        }

        $validated = $request->validate([
            'services' => 'required|array|min:1',
            'services.*.room_id'    => 'nullable|exists:rooms,id',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity'   => 'required|integer|min:1',
        ]);

        $booking->load(['services', 'rooms']);

        foreach ($validated['services'] as $srv) {
            $roomId     = $srv['room_id'] ?? null;
            $serviceId  = $srv['service_id'];
            $quantity   = $srv['quantity'];

            if ($roomId && !$booking->rooms->contains('id', $roomId)) {
                return response()->json([
                    'message' => "Phòng ID {$roomId} không thuộc booking này."
                ], 422);
            }

            $existing = DB::table('booking_service')
                ->where('booking_id', $booking->id)
                ->where('service_id', $serviceId)
                ->where(function ($query) use ($roomId) {
                    if (is_null($roomId)) {
                        $query->whereNull('room_id');
                    } else {
                        $query->where('room_id', $roomId);
                    }
                })
                ->first();

            if ($existing) {
                DB::table('booking_service')
                    ->where('id', $existing->id)
                    ->update([
                        'quantity'   => $existing->quantity + $quantity,
                        'updated_at' => now()
                    ]);
            } else {
                DB::table('booking_service')->insert([
                    'booking_id' => $booking->id,
                    'room_id'    => $roomId,
                    'service_id' => $serviceId,
                    'quantity'   => $quantity,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }

        $booking->load(['services', 'rooms.roomType', 'promotions']);
        $booking->recalculateTotal();

        return response()->json([
            'message' => 'Thêm dịch vụ thành công',
            'data'    => $booking->load(['services'])
        ]);
    }

    // thông tin khi checkin
    public function showCheckInInfo(Booking $booking)
    {
        $booking->load([
            'customer',
            'creator',
            'rooms.roomType.amenities',
            'services',
            'promotions',
            // chỉ lấy tiện nghi phát sinh của phòng thuộc đúng booking này
            'rooms.bookingAmenities' => function ($q) use ($booking) {
                $q->wherePivot('booking_id', $booking->id);
            },
        ]);

        return response()->json([
            'booking_id'      => $booking->id,
            'status'          => $booking->status,
            'check_in_date'   => $booking->check_in_date,
            'check_out_date'  => $booking->check_out_date,
            'deposit_amount'  => $booking->deposit_amount,
            'total_amount'    => $booking->total_amount,
            'raw_total'       => $booking->raw_total,
            'discount_amount' => $booking->discount_amount,

            'customer' => [
                'name'        => $booking->customer->name,
                'gender'      => $booking->customer->gender,
                'email'       => $booking->customer->email,
                'phone'       => $booking->customer->phone,
                'cccd'        => $booking->customer->cccd,
                'nationality' => $booking->customer->nationality,
                'address'     => $booking->customer->address,
            ],

            'rooms' => $booking->rooms->map(function ($room) {
                return [
                    'room_number' => $room->room_number,
                    'status'      => $room->status,
                    'image'       => $room->image,
                    'rate'        => $room->pivot->rate,

                    // tiện nghi mặc định theo loại phòng
                    'type' => [
                        'name'          => $room->roomType->name ?? null,
                        'max_occupancy' => $room->roomType->max_occupancy ?? null,
                        'amenities'     => $room->roomType->amenities->map(function ($amenity) {
                            return [
                                'name'     => $amenity->name,
                                'icon'     => $amenity->icon,
                                'quantity' => $amenity->pivot->quantity ?? 1,
                            ];
                        }),
                    ],

                    // tiện nghi PHÁT SINH cho CHÍNH phòng này trong booking hiện tại
                    'incurred_amenities' => $room->bookingAmenities->map(function ($a) {
                        $qty = (int) ($a->pivot->quantity ?? 0);
                        $price = (float) ($a->pivot->price ?? 0);
                        return [
                            'id'       => $a->id,
                            'name'     => $a->name,
                            'icon'     => $a->icon,
                            'price'    => $price,     // đơn giá tại thời điểm ghi nhận
                            'quantity' => $qty,
                            'note'     => $a->pivot->note,
                            'total'    => round($price * $qty, 2),
                        ];
                    }),
                ];
            }),

            'services' => $booking->services->map(function ($service) {
                return [
                    'name'        => $service->name,
                    'description' => $service->description,
                    'price'       => $service->price,
                    'quantity'    => $service->pivot->quantity,
                ];
            }),

            'created_by' => $booking->creator->name ?? null,
        ]);
    }


    /**
     * API thực hiện hành động check-in
     * POST /api/check-in/{id}
     */
    public function checkIn(Booking $booking)
    {
        if ($booking->status !== 'Confirmed') {
            return response()->json([
                'error' => 'Chỉ các booking đã được xác nhận mới được check-in.'
            ], 400);
        }

        $now = now();
        $checkInDate = Carbon::parse($booking->check_in_date)->toDateString();

        // Chỉ cho check-in đúng ngày nhận phòng
        if ($now->toDateString() !== $checkInDate) {
            return response()->json([
                'error' => "Chỉ được check-in trong ngày $checkInDate."
            ], 400);
        }

        DB::beginTransaction();
        try {
            $booking->status = 'Checked-in';
            $booking->check_in_at = $now;
            $booking->save();

            $booking->loadMissing('rooms');

            foreach ($booking->rooms as $room) {
                if (!in_array($room->status, ['available', 'booked'])) {
                    throw new \Exception("Phòng {$room->room_number} không khả dụng để check-in.");
                }
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


    public function checkOut(Booking $booking)
    {
        $booking->load(['rooms.roomType', 'services']);

        // Lấy & chuẩn hoá ngày nhận/trả phòng từ các cột khả dụng
        $ciRaw = $booking->check_in_date
            ?? $booking->check_in_at
            ?? $booking->start_date
            ?? $booking->arrival_at;

        $coRaw = $booking->check_out_date
            ?? $booking->check_out_at
            ?? $booking->end_date
            ?? $booking->departure_at;

        $checkIn  = $ciRaw ? Carbon::parse($ciRaw)->format('Y-m-d H:i:s') : null;
        $checkOut = $coRaw ? Carbon::parse($coRaw)->format('Y-m-d H:i:s') : null;

        try {
            $totals = $this->calculateBookingTotals($booking);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json([
            'message'         => 'Thông tin trước khi thực hiện checkout',
            'booking_id'      => $booking->id,
            // 🔹 Thêm 2 trường ngày cho FE
            'check_in_date'   => $checkIn,
            'check_out_date'  => $checkOut,

            'is_hourly'       => $totals['is_hourly'],
            'hours'           => $totals['hours'],

            'room_details'    => $totals['room_details'],
            'nights'          => $totals['nights'],
            'room_total'      => $totals['room_total'],
            'service_total'   => $totals['service_total'],
            'discount_amount' => $totals['discount'],
            'raw_total'       => $totals['raw_total'],
            'total_amount'    => $totals['total_amount'],
            'status'          => $booking->status,
        ]);
    }


    public function cancel(Booking $booking)
    {
        if (in_array($booking->status, ['Checked-out', 'Checked-in'])) {
            return response()->json([
                'error' => 'Không thể huỷ đơn đã nhận phòng hoặc đã trả phòng!'
            ], 400);
        }

        DB::transaction(function () use ($booking) {
            foreach ($booking->rooms as $room) {
                $room->update(['status' => 'available']);
            }
            $booking->update(['status' => 'Canceled']);
        });


        return response()->json([
            'message' => 'Huỷ đơn đặt phòng thành công!',
            'booking_id' => $booking->id,
            'status' => $booking->status
        ]);
    }

    public function payByCash(Booking $booking)
    {
        $booking->load(['rooms.roomType', 'services']);

        if ($booking->status === 'Checked-out') {
            return response()->json(['error' => 'Đơn này đã được thanh toán!'], 400);
        }

        try {
            DB::beginTransaction();

            // Với đặt giờ: dùng thời điểm hiện tại làm mốc kết thúc để tính
            $totals = $this->calculateBookingTotals($booking);

            // Chốt trả phòng
            $booking->status       = 'Checked-out';
            $booking->total_amount = $totals['total_amount'];
            $booking->check_out_at = now();
            $booking->save();

            foreach ($booking->rooms as $room) {
                $room->status = 'available';
                $room->save();
            }

            // Mã hoá đơn
            $today      = now()->format('Ymd');
            $countToday = Invoice::whereDate('issued_date', today())->count() + 1;
            $invoiceCode = 'INV-' . $today . '-' . str_pad($countToday, 3, '0', STR_PAD_LEFT);

            // Tạo hoá đơn (có amenity_amount)
            $invoice = Invoice::create([
                'invoice_code'    => $invoiceCode,
                'booking_id'      => $booking->id,
                'issued_date'     => now(),
                'room_amount'     => $totals['room_total'],
                'service_amount'  => $totals['service_total'],
                'amenity_amount'  => $totals['amenity_total'],
                'discount_amount' => $totals['discount'],
                'deposit_amount'  => $totals['deposit_amount'],
                'total_amount'    => $totals['total_amount'],
            ]);

            // Ghi nhận thanh toán
            Payment::create([
                'invoice_id'       => $invoice->id,
                'amount'           => $totals['total_amount'],
                'method'           => 'cash',
                'transaction_code' => null,
                'paid_at'          => now(),
                'status'           => 'success',
            ]);

            DB::commit();

            return response()->json([
                'message'         => 'Thanh toán tiền mặt và trả phòng thành công!',
                'booking_id'      => $booking->id,
                'is_hourly'       => $totals['is_hourly'],
                'hours'           => $totals['hours'],
                'nights'          => $totals['nights'],
                'room_details'    => $totals['room_details'],
                'room_total'      => $totals['room_total'],
                'service_total'   => $totals['service_total'],
                'amenity_total'   => $totals['amenity_total'],
                'discount_amount' => $totals['discount'],
                'raw_total'       => $totals['raw_total'],
                'deposit_paid'    => $totals['is_deposit_paid'] ? 'yes' : 'no',
                'deposit_amount'  => $totals['deposit_amount'],
                'total_amount'    => $totals['total_amount'],
                'invoice_id'      => $invoice->id,
                'status'          => $booking->status,
                'check_out_at'    => $booking->check_out_at,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => 'Lỗi khi thanh toán: ' . $e->getMessage()], 500);
        }
    }


    /**
     * Tính tổng tiền của booking (phòng, dịch vụ, tiện nghi), giảm giá, đặt cọc.
     * - Services: từ quan hệ $booking->services (pivot quantity, có thể có pivot->price)
     * - Amenities: từ bảng booking_room_amenities (fallback về giá trong bảng amenities nếu row->price null)
     * Trả về mảng chi tiết + tổng tiền cuối cùng.
     */
    private function calculateBookingTotals(Booking $booking): array
    {
        $booking->loadMissing(['rooms.roomType', 'services']);

        $isHourly = (int)($booking->is_hourly ?? 0) === 1;

        if ($isHourly) {
            // ----- Theo giờ -----
            // GIỮ GIỜ THỰC từ DB, KHÔNG startOfDay
            $startRaw = $booking->check_in_date;
            $endRaw   = $booking->check_out_date ?: now();

            $start = Carbon::parse($startRaw)->startOfMinute();
            $end   = Carbon::parse($endRaw)->startOfMinute();

            // diff âm => dữ liệu sai
            $minutes = $start->diffInMinutes($end, false);
            if ($minutes < 0) {
                throw new \InvalidArgumentException('Thời gian trả phòng nhỏ hơn nhận phòng.');
            }

            $hours = max(1, (int) ceil($minutes / 60));


            $roomTotal   = 0.0;
            $roomDetails = [];
            foreach ($booking->rooms as $room) {
                $rate  = (float)($room->roomType->hourly_rate ?? 0);
                $total = round($rate * $hours, 0);
                $roomTotal += $total;

                $roomDetails[] = [
                    'room_id'     => $room->id,
                    'room_number' => $room->room_number,
                    'unit'        => 'hour',
                    'unit_count'  => $hours,
                    'base_rate'        => $rate,
                    'total'       => $total,
                ];
            }
            $nights = 0;
        } else {
            // ----- Theo đêm -----
            $checkIn  = Carbon::parse($booking->check_in_date)->startOfDay();
            $checkOut = Carbon::parse($booking->check_out_date)->startOfDay();
            if ($checkOut->lt($checkIn)) {
                throw new \InvalidArgumentException('Ngày check-out không hợp lệ (trước ngày check-in)');
            }
            $nights = max(0, $checkIn->diffInDays($checkOut));

            $roomTotal   = 0.0;
            $roomDetails = [];
            foreach ($booking->rooms as $room) {
                $rate  = (float)($room->roomType->base_rate ?? 0);
                $total = round($rate * $nights, 0);
                $roomTotal += $total;

                $roomDetails[] = [
                    'room_id'     => $room->id,
                    'room_number' => $room->room_number,
                    'unit'        => 'night',
                    'unit_count'  => $nights,
                    'base_rate'        => $rate,
                    'total'       => $total,
                ];
            }
            $hours = 0;
        }

        // ----- Dịch vụ -----
        $serviceTotal = 0.0;
        foreach ($booking->services as $service) {
            $qty  = (int)($service->pivot->quantity ?? 1);
            $unit = isset($service->pivot->price) ? (float)$service->pivot->price : (float)($service->price ?? 0);
            $serviceTotal += round($unit * $qty, 0);
        }

        // ----- Tiện nghi phát sinh -----
        $amenityRows = BookingRoomAmenity::with(['room:id,room_number', 'amenity:id,name,price'])
            ->where('booking_id', $booking->id)
            ->get();

        $amenityTotal   = 0.0;
        $amenityDetails = [];
        foreach ($amenityRows as $row) {
            $qty  = (int)($row->quantity ?? 1);
            $unit = is_null($row->price) ? (float)optional($row->amenity)->price : (float)$row->price;
            $line = round($unit * $qty, 0);
            $amenityTotal += $line;

            $amenityDetails[] = [
                'room_id'      => $row->room_id,
                'room_number'  => optional($row->room)->room_number,
                'amenity_id'   => $row->amenity_id,
                'amenity_name' => optional($row->amenity)->name,
                'price'        => $unit,
                'quantity'     => $qty,
                'total'        => $line,
                'created_at'   => optional($row->created_at)?->toDateTimeString(),
            ];
        }

        // ----- Tổng hợp -----
        $discount      = (float)($booking->discount_amount ?? 0);
        $rawTotal      = round($roomTotal + $serviceTotal + $amenityTotal, 0);
        $subtotal      = max(0, $rawTotal - $discount);

        $depositAmount = (float)($booking->deposit_amount ?? 0);
        $isDepositPaid = (int)($booking->is_deposit_paid ?? 0);
        $finalTotal    = $isDepositPaid ? max(0, $subtotal - $depositAmount) : $subtotal;

        return [
            'is_hourly'        => $isHourly ? 1 : 0,
            'hours'            => $hours,
            'nights'           => $nights,

            'room_details'     => $roomDetails,
            'amenity_details'  => $amenityDetails,

            'room_total'       => $roomTotal,
            'service_total'    => $serviceTotal,
            'amenity_total'    => $amenityTotal,

            'discount'         => $discount,
            'raw_total'        => $rawTotal,
            'total_amount'     => $finalTotal,

            'deposit_amount'   => $depositAmount,
            'is_deposit_paid'  => $isDepositPaid,
        ];
    }

    public function removeService(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'room_id'    => 'nullable|exists:rooms,id',
        ]);

        $booking->load('rooms');

        if (!empty($validated['room_id'])) {
            if (!$booking->rooms->contains('id', $validated['room_id'])) {
                return response()->json([
                    'message' => "Phòng ID {$validated['room_id']} không thuộc booking này."
                ], 422);
            }

            DB::table('booking_service')
                ->where('booking_id', $booking->id)
                ->where('service_id', $validated['service_id'])
                ->where('room_id', $validated['room_id'])
                ->delete();
        } else {
            DB::table('booking_service')
                ->where('booking_id', $booking->id)
                ->where('service_id', $validated['service_id'])
                ->whereNull('room_id')
                ->delete();
        }

        $booking->recalculateTotal();

        return response()->json([
            'message' => 'Xoá dịch vụ thành công',
            'data' => $booking->load('services')
        ]);
    }
    public function payDeposit(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:cash,transfer',
            'transaction_code' => 'nullable|string|max:100'
        ]);

        if ($booking->is_deposit_paid) {
            return response()->json(['message' => 'Đặt cọc đã được thanh toán trước đó.'], 422);
        }

        DB::beginTransaction();
        try {
            Payment::create([
                'invoice_id'       => null,
                'booking_id'       => $booking->id,
                'amount'           => $validated['amount'],
                'method'           => $validated['method'],
                'transaction_code' => $validated['transaction_code'],
                'paid_at'          => now(),
                'status'           => 'success',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            $booking->update([
                'deposit_amount'   => $validated['amount'],
                'is_deposit_paid'  => true,
                'status'           => 'Confirmed'
            ]);

            DB::commit();

            return response()->json([
                'message'    => 'Xác nhận thanh toán đặt cọc thành công',
                'booking_id' => $booking->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi khi xử lý thanh toán: ' . $e->getMessage()
            ], 500);
        }
    }

    // app/Http/Controllers/BookingController.php
    public function amenityOptions(Booking $booking)
    {
        $booking->load([
            'rooms:id,room_number,room_type_id',
            'rooms.roomType.amenities:id,name,price',
            'rooms.bookingAmenities' => fn($q) => $q->wherePivot('booking_id', $booking->id)
        ]);

        return response()->json([
            'rooms' => $booking->rooms->map(fn($room) => [
                'id'          => (int) $room->id,
                'room_number' => $room->room_number,
                'amenities'   => ($room->roomType?->amenities ?? collect())->map(fn($a) => [
                    'id' => (int) $a->id,
                    'name' => $a->name,
                    'price' => (int) ($a->price ?? 0),
                ])->values(),
            ])->values(),

            'incurred' => $booking->rooms->map(fn($room) => [
                'room_id' => (int) $room->id,
                'items'   => $room->bookingAmenities
                    ->where('pivot.booking_id', $booking->id)
                    ->map(fn($a) => [
                        'amenity_id' => (int) $a->id,
                        'name'       => $a->name,
                        'price'      => (int) ($a->pivot->price ?? 0),
                        'quantity'   => (int) ($a->pivot->quantity ?? 1),
                        'subtotal'   => (int) (($a->pivot->price ?? 0) * ($a->pivot->quantity ?? 1)),
                    ])->values(),
            ])->values(),
        ]);
    }

    // public function roomAmenities(Booking $booking, Room $room)
    // {
    //     abort_unless($booking->rooms()->whereKey($room->id)->exists(), 404);
    //     $room->load('roomType.amenities:id,name,price');

    //     return response()->json([
    //         'room_id'   => (int) $room->id,
    //         'amenities' => $room->roomType?->amenities->map(fn($a) => [
    //             'id' => (int) $a->id,
    //             'name' => $a->name,
    //             'price' => (int) ($a->price ?? 0),
    //         ])->values() ?? [],
    //     ]);
    // }

    public function storeAmenitiesIncurred(Request $req, Booking $booking)
    {
        $data = $req->validate([
            'items' => 'required|array|min:1',
            'items.*.room_id'    => 'required|integer|exists:rooms,id',
            'items.*.amenity_id' => 'required|integer|exists:amenities,id',
            'items.*.price'      => 'required|integer|min:0',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        $items = $data['items'] ?? [];
        if (count($items) === 0) {
            // Không có tiện nghi phát sinh -> coi như OK
            return response()->json(['message' => 'No incurred amenities'], 200);
        }

        foreach ($data['items'] as $row) {
            abort_unless($booking->rooms()->whereKey($row['room_id'])->exists(), 422);

            BookingRoomAmenity::updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'room_id'    => $row['room_id'],
                    'amenity_id' => $row['amenity_id'],
                ],
                [
                    'price'    => $row['price'],
                    'quantity' => $row['quantity'],
                ]
            );
        }

        return response()->json(['status' => 'ok']);
    }

    public function servicesUsed(Booking $booking)
    {
        // Eager-load để tránh N+1
        $items = $booking->serviceUsages()
            ->with([
                'room:id,room_number',             // đổi cột nếu khác
                'service:id,name,price',           // đổi cột giá nếu là unit_price
            ])
            ->orderBy('created_at', 'asc')
            ->get();

        // Chuẩn hóa về format mà FE đang đọc
        $data = $items->map(function ($bs) {
            return [
                'name'        => optional($bs->service)->name ?? '',
                'room_number' => optional($bs->room)->room_number ?? '',
                'price'       => (int) (optional($bs->service)->price ?? 0), // nếu có cột snapshot giá ở booking_service thì ưu tiên bs->price
                'quantity'    => (int) $bs->quantity,
                'created_at'  => optional($bs->created_at)->format('Y-m-d H:i:s'),
            ];
        })->values();

        return response()->json($data);
    }
}
