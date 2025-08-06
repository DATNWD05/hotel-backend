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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

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

    /**
     * Hiển thị chi tiết một đơn đặt phòng.
     */
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

    /**
     * Lấy danh sách phòng khả dụng dựa trên loại phòng và khoảng thời gian.
     */
    public function getAvailableRooms(Request $request)
    {
        $validated = $request->validate([
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
            'room_type_id' => 'sometimes|exists:room_types,id', // Tham số tùy chọn
        ]);

        $query = Room::where('status', '!=', 'maintenance')
            ->when($validated['room_type_id'] ?? null, function ($q) use ($validated) {
                return $q->where('room_type_id', $validated['room_type_id']);
            });

        $rooms = $query->get()->filter(function ($room) use ($validated) {
            return !DB::table('booking_room')
                ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
                ->where('booking_room.room_id', $room->id)
                ->whereIn('bookings.status', ['Pending', 'Confirmed', 'Checked-in'])
                ->where(function ($query) use ($validated) {
                    $query->whereBetween('bookings.check_in_date', [$validated['check_in_date'], $validated['check_out_date']])
                        ->orWhereBetween('bookings.check_out_date', [$validated['check_in_date'], $validated['check_out_date']])
                        ->orWhere(function ($q) use ($validated) {
                            $q->where('bookings.check_in_date', '<=', $validated['check_in_date'])
                                ->where('bookings.check_out_date', '>=', $validated['check_out_date']);
                        });
                })
                ->exists();
        });

        return response()->json([
            'data' => $rooms->values()->map(function ($room) {
                return [
                    'id' => $room->id,
                    'room_number' => $room->room_number,
                    'status' => $room->status,
                    'room_type_id' => $room->room_type_id,
                ];
            })
        ]);
    }

    /**
     * Kiểm tra phòng khả dụng trước khi đặt.
     */
    public function validateRooms(Request $request)
    {
        $validated = $request->validate([
            'room_ids' => 'required|array|min:1',
            'room_ids.*' => 'required|exists:rooms,id',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',
        ]);

        $roomIds = $validated['room_ids'];
        $checkInDate = $validated['check_in_date'];
        $checkOutDate = $validated['check_out_date'];

        $unavailableRooms = [];
        foreach ($roomIds as $roomId) {
            $conflict = DB::table('booking_room')
                ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
                ->where('booking_room.room_id', $roomId)
                ->whereIn('bookings.status', ['Pending', 'Confirmed', 'Checked-in'])
                ->where(function ($query) use ($checkInDate, $checkOutDate) {
                    $query->whereBetween('bookings.check_in_date', [$checkInDate, $checkOutDate])
                        ->orWhereBetween('bookings.check_out_date', [$checkInDate, $checkOutDate])
                        ->orWhere(function ($q) use ($checkInDate, $checkOutDate) {
                            $q->where('bookings.check_in_date', '<=', $checkInDate)
                                ->where('bookings.check_out_date', '>=', $checkOutDate);
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
            'services.*.room_id'     => 'nullable|exists:rooms,id',

            'promotion_code'         => 'nullable|string|exists:promotions,code',
            'deposit_amount'         => 'nullable|numeric|min:0',
        ], [
            'customer.date_of_birth.before_or_equal' => 'Khách hàng phải đủ 18 tuổi mới được đặt phòng.',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            foreach ($validated['room_ids'] as $roomId) {
                $conflict = DB::table('booking_room')
                    ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
                    ->where('booking_room.room_id', $roomId)
                    ->whereIn('bookings.status', ['Pending', 'Confirmed', 'Checked-in'])
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
                    throw new \Exception("Phòng ID {$roomId} đã được đặt trong thời gian này.", 422);
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
                'total_amount'    => max(0, $rawTotal - $discount),
                'deposit_amount'  => $validated['deposit_amount'] ?? 0,
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
        });
    }

    /**
     * Cập nhật đơn đặt phòng.
     */
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

            'room_ids'               => 'sometimes|array|min:1',
            'room_ids.*'             => 'exists:rooms,id',

            'check_in_date'          => 'sometimes|date|after_or_equal:today',
            'check_out_date'         => 'sometimes|date|after:check_in_date',

            'check_in_at'            => 'nullable|date',
            'check_out_at'           => 'nullable|date|after_or_equal:check_in_at',

            'services'               => 'nullable|array',
            'services.*.service_id'  => 'required_with:services|exists:services,id',
            'services.*.quantity'    => 'required_with:services|integer|min:1',
            'services.*.room_id'     => 'nullable|exists:rooms,id',

            'promotion_code'         => 'nullable|string|exists:promotions,code',
            'deposit_amount'         => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request, $validated, $booking) {
            if ($request->hasAny(['room_ids', 'check_in_date', 'check_out_date'])) {
                $newRoomIds = $validated['room_ids'] ?? $booking->rooms->pluck('id')->toArray();
                $newCheckIn = $validated['check_in_date'] ?? $booking->check_in_date;
                $newCheckOut = $validated['check_out_date'] ?? $booking->check_out_date;

                foreach ($newRoomIds as $roomId) {
                    $conflict = DB::table('booking_room')
                        ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
                        ->where('booking_room.room_id', $roomId)
                        ->where('bookings.id', '!=', $booking->id)
                        ->whereIn('bookings.status', ['Pending', 'Confirmed', 'Checked-in'])
                        ->where(function ($query) use ($newCheckIn, $newCheckOut) {
                            $query->whereBetween('bookings.check_in_date', [$newCheckIn, $newCheckOut])
                                ->orWhereBetween('bookings.check_out_date', [$newCheckIn, $newCheckOut])
                                ->orWhere(function ($q) use ($newCheckIn, $newCheckOut) {
                                    $q->where('bookings.check_in_date', '<=', $newCheckIn)
                                        ->where('bookings.check_out_date', '>=', $newCheckOut);
                                });
                        })
                        ->exists();

                    if ($conflict) {
                        throw new \Exception("Phòng ID {$roomId} đã được đặt trong thời gian bạn chọn.", 422);
                    }
                }
            }

            if ($request->has('customer')) {
                $customerData = $validated['customer'];
                $customer = Customer::updateOrCreate(
                    ['cccd' => $customerData['cccd']],
                    $customerData
                );
                $booking->customer_id = $customer->id;
            } else {
                $customer = $booking->customer;
            }

            $booking->fill([
                'check_in_date'  => $validated['check_in_date'] ?? $booking->check_in_date,
                'check_out_date' => $validated['check_out_date'] ?? $booking->check_out_date,
                'check_in_at'    => $validated['check_in_at'] ?? $booking->check_in_at,
                'check_out_at'   => $validated['check_out_at'] ?? $booking->check_out_at,
            ]);

            $nights = Carbon::parse($booking->check_in_date)->diffInDays($booking->check_out_date);
            $roomTotal = 0;

            if ($request->has('room_ids')) {
                $rooms = Room::with('roomType')->whereIn('id', $validated['room_ids'])->get();
                $roomsData = $rooms->mapWithKeys(function ($room) use (&$roomTotal, $nights) {
                    $rate = $room->roomType->base_rate ?? 0;
                    $roomTotal += $rate * $nights;
                    return [$room->id => ['rate' => $rate]];
                });
                $booking->rooms()->sync($roomsData);
            } else {
                foreach ($booking->rooms as $room) {
                    $roomTotal += ($room->roomType->base_rate ?? 0) * $nights;
                }
            }

            $serviceTotal = 0;
            if ($request->has('services')) {
                $booking->services()->detach();
                foreach ($validated['services'] as $srv) {
                    $service = Service::findOrFail($srv['service_id']);
                    $subtotal = $service->price * $srv['quantity'];
                    $serviceTotal += $subtotal;
                    $booking->services()->attach($srv['service_id'], [
                        'quantity' => $srv['quantity'],
                        'room_id'  => $srv['room_id'] ?? null
                    ]);
                }
            } else {
                foreach ($booking->services as $srv) {
                    $serviceTotal += $srv->price * $srv->pivot->quantity;
                }
            }

            $rawTotal = $roomTotal + $serviceTotal;

            $discount = 0;
            if ($request->has('promotion_code')) {
                $promotion = Promotion::where('code', $validated['promotion_code'])->first();
                if ($promotion && $promotion->isValid()) {
                    $discount = $promotion->discount_type === 'percent'
                        ? $rawTotal * ($promotion->discount_value / 100)
                        : $promotion->discount_value;
                    $promotion->increment('used_count');
                    $booking->promotions()->sync([
                        $promotion->id => [
                            'promotion_code' => $promotion->code,
                            'applied_at'     => now(),
                        ]
                    ]);
                }
            } else {
                $booking->promotions()->detach();
            }

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

            $oldDeposit = $booking->deposit_amount;
            if ($request->has('deposit_amount')) {
                $newDeposit = $validated['deposit_amount'];
                if ($oldDeposit != $newDeposit && $newDeposit > 0) {
                    $depositUrl = route('deposit.vnpay.create', ['booking_id' => $booking->id]);
                    Mail::to($customer->email)->send(new DepositLinkMail($booking, $depositUrl));
                }
                $booking->deposit_amount = $newDeposit;
            }

            $booking->raw_total = $rawTotal;
            $booking->discount_amount = $discount;
            $booking->total_amount = max(0, $rawTotal - $discount);
            $booking->save();

            return response()->json([
                'message' => 'Cập nhật đơn đặt phòng thành công',
                'data' => $booking->fresh()->load(['customer', 'rooms.roomType', 'services', 'promotions']),
            ]);
        });
    }

    /**
     * Thêm dịch vụ vào đơn đặt phòng.
     */
    public function addServices(Request $request, Booking $booking)
    {
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

        return DB::transaction(function () use ($validated, $booking) {
            $booking->load(['services', 'rooms']);

            foreach ($validated['services'] as $srv) {
                $roomId = $srv['room_id'] ?? null;
                $serviceId = $srv['service_id'];
                $quantity = $srv['quantity'];

                if ($roomId && !$booking->rooms->contains('id', $roomId)) {
                    throw new \Exception("Phòng ID {$roomId} không thuộc booking này.", 422);
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

            $booking->recalculateTotal();

            return response()->json([
                'message' => 'Thêm dịch vụ thành công',
                'data' => $booking->load(['services'])
            ]);
        });
    }

    /**
     * Hiển thị thông tin check-in.
     */
    public function showCheckInInfo(Booking $booking)
    {
        $booking->load([
            'customer',
            'creator',
            'rooms.roomType.amenities',
            'services',
            'promotions'
        ]);

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
     * Thực hiện check-in.
     */
    public function checkIn(Booking $booking)
    {
        if (!in_array($booking->status, ['Pending', 'Confirmed'])) {
            return response()->json([
                'error' => 'Booking hiện không ở trạng thái cho phép check-in'
            ], 400);
        }

        return DB::transaction(function () use ($booking) {
            $booking->status = 'Checked-in';
            $booking->check_in_at = now();
            $booking->save();

            foreach ($booking->rooms as $room) {
                $room->update(['status' => 'booked']);
            }

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
        });
    }

    /**
     * Hiển thị thông tin trước khi check-out.
     */
    public function checkOut(Booking $booking)
    {
        $booking->load(['rooms.roomType', 'services']);

        try {
            $totals = $this->calculateBookingTotals($booking);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json([
            'message' => 'Thông tin trước khi thực hiện checkout',
            'booking_id' => $booking->id,
            'room_details' => $totals['room_details'],
            'nights' => $totals['nights'],
            'room_total' => $totals['room_total'],
            'service_total' => $totals['service_total'],
            'discount_amount' => $totals['discount'],
            'raw_total' => $totals['raw_total'],
            'total_amount' => $totals['total_amount'],
            'status' => $booking->status,
        ]);
    }

    /**
     * Hủy đơn đặt phòng.
     */
    public function cancel(Booking $booking)
    {
        if (in_array($booking->status, ['Checked-out', 'Checked-in'])) {
            return response()->json([
                'error' => 'Không thể hủy đơn đã nhận phòng hoặc đã trả phòng!'
            ], 400);
        }

        return DB::transaction(function () use ($booking) {
            $booking->status = 'Canceled';
            $booking->save();

            foreach ($booking->rooms as $room) {
                $room->update(['status' => 'available']);
            }

            return response()->json([
                'message' => 'Hủy đơn đặt phòng thành công!',
                'booking_id' => $booking->id,
                'status' => $booking->status
            ]);
        });
    }

    /**
     * Thanh toán bằng tiền mặt và check-out.
     */
    public function payByCash(Booking $booking)
    {
        if ($booking->status === 'Checked-out') {
            return response()->json(['error' => 'Đơn này đã được thanh toán!'], 400);
        }

        return DB::transaction(function () use ($booking) {
            $booking->load(['rooms.roomType', 'services']);

            $totals = $this->calculateBookingTotals($booking);

            $booking->status = 'Checked-out';
            $booking->total_amount = $totals['total_amount'];
            $booking->check_out_at = now();
            $booking->save();

            foreach ($booking->rooms as $room) {
                $room->update(['status' => 'available']);
            }

            $today = now()->format('Ymd');
            $countToday = Invoice::whereDate('issued_date', today())->count() + 1;
            $invoiceCode = 'INV-' . $today . '-' . str_pad($countToday, 3, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'invoice_code' => $invoiceCode,
                'booking_id' => $booking->id,
                'issued_date' => now(),
                'room_amount' => $totals['room_total'],
                'service_amount' => $totals['service_total'],
                'discount_amount' => $totals['discount'],
                'deposit_amount' => $totals['deposit_amount'],
                'total_amount' => $totals['total_amount'],
            ]);

            Payment::create([
                'invoice_id' => $invoice->id,
                'booking_id' => $booking->id,
                'amount' => $totals['total_amount'],
                'method' => 'cash',
                'transaction_code' => null,
                'paid_at' => now(),
                'status' => 'success',
            ]);

            return response()->json([
                'message' => 'Thanh toán tiền mặt và trả phòng thành công!',
                'booking_id' => $booking->id,
                'nights' => $totals['nights'],
                'room_details' => $totals['room_details'],
                'room_total' => $totals['room_total'],
                'service_total' => $totals['service_total'],
                'discount_amount' => $totals['discount'],
                'raw_total' => $totals['raw_total'],
                'deposit_paid' => $totals['is_deposit_paid'] ? 'yes' : 'no',
                'deposit_amount' => $totals['deposit_amount'],
                'total_amount' => $totals['total_amount'],
                'invoice_id' => $invoice->id,
                'status' => $booking->status,
                'check_out_at' => $booking->check_out_at,
            ]);
        });
    }

    /**
     * Tính toán tổng chi phí của đơn đặt phòng.
     */
    private function calculateBookingTotals(Booking $booking)
    {
        $checkIn = Carbon::parse($booking->check_in_date);
        $checkOut = Carbon::parse($booking->check_out_date);

        if ($checkOut->lt($checkIn)) {
            throw new \Exception('Ngày check-out không hợp lệ (trước ngày check-in)');
        }

        $nights = $checkIn->diffInDays($checkOut);
        $roomTotal = 0;
        $roomDetails = [];

        foreach ($booking->rooms as $room) {
            $rate = floatval(optional($room->roomType)->base_rate ?? 0);
            $total = $rate * $nights;
            $roomTotal += $total;

            $roomDetails[] = [
                'room_number' => $room->room_number,
                'base_rate' => $rate,
                'total' => $total,
            ];
        }

        $serviceTotal = 0;
        foreach ($booking->services as $service) {
            $quantity = intval($service->pivot->quantity ?? 1);
            $serviceTotal += floatval($service->price) * $quantity;
        }

        $discountAmount = floatval($booking->discount_amount ?? 0);
        $rawTotal = $roomTotal + $serviceTotal;
        $totalAmount = $rawTotal - $discountAmount;

        $depositAmount = floatval($booking->deposit_amount ?? 0);
        $isDepositPaid = boolval($booking->is_deposit_paid ?? false);
        $finalTotal = $isDepositPaid ? max(0, $totalAmount - $depositAmount) : $totalAmount;

        return [
            'nights' => $nights,
            'room_details' => $roomDetails,
            'room_total' => $roomTotal,
            'service_total' => $serviceTotal,
            'discount' => $discountAmount,
            'raw_total' => $rawTotal,
            'total_amount' => $finalTotal,
            'deposit_amount' => $depositAmount,
            'is_deposit_paid' => $isDepositPaid,
        ];
    }

    /**
     * Xóa dịch vụ khỏi đơn đặt phòng.
     */
    public function removeService(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'room_id'    => 'nullable|exists:rooms,id',
        ]);

        return DB::transaction(function () use ($validated, $booking) {
            $booking->load('rooms');

            if (!empty($validated['room_id']) && !$booking->rooms->contains('id', $validated['room_id'])) {
                throw new \Exception("Phòng ID {$validated['room_id']} không thuộc booking này.", 422);
            }

            DB::table('booking_service')
                ->where('booking_id', $booking->id)
                ->where('service_id', $validated['service_id'])
                ->where(function ($query) use ($validated) {
                    if (empty($validated['room_id'])) {
                        $query->whereNull('room_id');
                    } else {
                        $query->where('room_id', $validated['room_id']);
                    }
                })
                ->delete();

            $booking->recalculateTotal();

            return response()->json([
                'message' => 'Xóa dịch vụ thành công',
                'data' => $booking->load('services')
            ]);
        });
    }

    /**
     * Thanh toán đặt cọc.
     */
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

        return DB::transaction(function () use ($validated, $booking) {
            Payment::create([
                'invoice_id' => null,
                'booking_id' => $booking->id,
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'transaction_code' => $validated['transaction_code'],
                'paid_at' => now(),
                'status' => 'success',
            ]);

            $booking->update([
                'deposit_amount' => $validated['amount'],
                'is_deposit_paid' => true,
                'status' => 'Confirmed'
            ]);

            return response()->json([
                'message' => 'Xác nhận thanh toán đặt cọc thành công',
                'booking_id' => $booking->id
            ]);
        });
    }
}
