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

class BookingController extends Controller
{
    /**
     * Hiển thị danh sách tất cả các đơn đặt phòng.
     */
    public function index()
    {
        return Booking::with(['customer', 'room', 'creator'])->get();
    }

    /**
     * Hiển thị chi tiết một đơn đặt phòng cụ thể.
     */
    public function show(Booking $booking)
    {
        return $booking->load(['customer', 'room', 'creator']);
    }

    /**
     * Tạo mới một đơn đặt phòng.
     */
    public function store(Request $request)
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

            'room_id'               => 'required|exists:rooms,id',
            'check_in_date'         => 'required|date|after_or_equal:today',
            'check_out_date'        => 'required|date|after:check_in_date',

            'services'              => 'nullable|array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity'   => 'required|integer|min:1',

            'promotion_code'        => 'nullable|string|exists:promotions,code',
            'deposit_amount'        => 'nullable|numeric|min:0',
        ]);

        // Tạo hoặc lấy khách hàng
        $customer = Customer::where('cccd', $validated['customer']['cccd'])->first();

        if (!$customer) {
            $customer = Customer::create($validated['customer']);
        } else {
            $customer->update($validated['customer']);
        }


        $user = Auth::user();
        $nights = Carbon::parse($validated['check_in_date'])->diffInDays($validated['check_out_date']);

        $room = Room::with('roomType')->findOrFail($validated['room_id']);
        $roomRate = $room->roomType->base_rate ?? 0;
        $roomTotal = $roomRate * $nights;

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

        // Tạo đơn đặt phòng
        $booking = Booking::create([
            'customer_id'     => $customer->id,
            'room_id'         => $room->id,
            'created_by'      => $user->id,
            'check_in_date'   => $validated['check_in_date'],
            'check_out_date'  => $validated['check_out_date'],
            'status'          => 'Pending',
            'raw_total'       => $rawTotal,
            'discount_amount' => $discount,
            'total_amount'    => max(0, $rawTotal - $discount),
            'deposit_amount'  => $validated['deposit_amount'] ?? 0,
        ]);

        // Gắn dịch vụ
        if (!empty($services)) {
            $booking->services()->attach($services);
        }

        // Gắn khuyến mãi
        if ($promotion) {
            $booking->promotions()->attach($promotion->id, [
                'promotion_code' => $promotion->code,
                'applied_at'     => now(),
            ]);
        }

        return response()->json([
            'message' => 'Đặt phòng thành công',
            'data' => $booking->load(['customer', 'services', 'promotions']),
            'room_total' => $roomTotal,
            'service_total' => $serviceTotal,
        ]);
    }
}
