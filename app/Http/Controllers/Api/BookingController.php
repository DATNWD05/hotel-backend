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
        // Validate dữ liệu
        $validated = $request->validate([
            'customer.cccd' => 'required|string|max:20',
            'customer.name' => 'required|string|max:100',
            'customer.gender' => 'required|in:Male,Female,Other',
            'customer.email' => 'required|email',
            'customer.phone' => 'required|string|max:20',
            'customer.date_of_birth' => 'required|date',
            'customer.nationality' => 'required|string|max:100',
            'customer.address' => 'nullable|string',
            'customer.note' => 'nullable|string',

            'room_id' => 'required|exists:rooms,id',
            'check_in_date' => 'required|date|after_or_equal:today',
            'check_out_date' => 'required|date|after:check_in_date',

            'services' => 'nullable|array',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'required|integer|min:1',

            'promotion_code' => 'nullable|string|exists:promotions,code',
        ]);

        // Tạo hoặc lấy khách hàng
        $customer = Customer::firstOrCreate(
            ['cccd' => $validated['customer']['cccd']],
            $validated['customer']
        );

        $user = Auth::user();
        $nights = Carbon::parse($validated['check_in_date'])
            ->diffInDays($validated['check_out_date']);

        $room = Room::findOrFail($validated['room_id']);
        $roomRate = $room->rate;
        $roomTotal = $roomRate * $nights;

        // Tính tiền dịch vụ
        $serviceTotal = 0;
        $services = [];
        if (!empty($validated['services'])) {
            foreach ($validated['services'] as $srv) {
                $service = Service::findOrFail($srv['service_id']);
                $subtotal = $service->price * $srv['quantity'];
                $serviceTotal += $subtotal;
                $services[$srv['service_id']] = ['quantity' => $srv['quantity']];
            }
        }

        $rawTotal = $roomTotal + $serviceTotal;

        // Tính khuyến mãi nếu có
        $discount = 0;
        $promotion = null;
        if (!empty($validated['promotion_id'])) {
            $promotion = Promotion::where('id', $validated['promotion_id'])->first();
            if ($promotion && $promotion->isValid()) {
                $discount = $promotion->discount_type === 'percent'
                    ? $rawTotal * ($promotion->discount_value / 100)
                    : $promotion->discount_value;
            }
        }

        // Tạo đơn đặt phòng
        $booking = Booking::create([
            'customer_id'     => $customer->id,
            'room_id'         => $validated['room_id'],
            'created_by'      => $user->id,
            'check_in_date'   => $validated['check_in_date'],
            'check_out_date'  => $validated['check_out_date'],
            'status'          => 'Pending',
            'raw_total'       => $rawTotal,
            'discount_amount' => $discount,
            'total_amount'    => max(0, $rawTotal - $discount),
        ]);

        // Gắn dịch vụ nếu có
        if (!empty($services)) {
            $booking->services()->attach($services); // pivot: booking_id, service_id, quantity
        }

        // Gắn khuyến mãi nếu có
        if ($promotion) {
            $booking->promotions()->attach($promotion->id, [
                'promotion_id' => $promotion->code,
                'applied_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Đặt phòng thành công',
            'data' => $booking->load(['customer', 'services', 'promotions']),
        ]);
    }
}
