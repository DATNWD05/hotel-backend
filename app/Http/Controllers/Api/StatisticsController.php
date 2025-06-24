<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Booking;
use App\Models\Room;

class StatisticsController extends Controller
{
    // 1. Tổng doanh thu toàn hệ thống (đã gồm cả dịch vụ trong total_amount)
    public function totalRevenue()
    {
        $total = DB::table('bookings')->sum('total_amount');

        return response()->json([
            'total_revenue' => $total
        ]);
    }

    // 2. Doanh thu theo ngày
    public function revenueByDay()
    {
        $revenue = DB::table('bookings')
            ->select(DB::raw('DATE(check_in_date) as date'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->get();

        return response()->json($revenue);
    }

    // 3. Tổng chi phí từng booking
    public function totalPerBooking()
    {
        $data = DB::table('bookings')
            ->select('id as booking_id', 'total_amount')
            ->get();

        return response()->json($data);
    }

    // 4. Tổng doanh thu theo khách hàng
    public function revenueByCustomer()
    {
        $data = DB::table('bookings')
            ->join('customers', 'bookings.customer_id', '=', 'customers.id')
            ->select('customers.name', DB::raw('SUM(bookings.total_amount) as total_spent'))
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        return response()->json($data);
    }

    // 5. Tổng doanh thu theo phòng
    public function revenueByRoom()
    {
        $data = DB::table('bookings')
            ->join('rooms', 'bookings.room_id', '=', 'rooms.id')
            ->select('rooms.room_number', DB::raw('SUM(bookings.total_amount) as total_revenue'))
            ->groupBy('rooms.id', 'rooms.room_number')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        return response()->json($data);
    }

    // 6. Tỷ lệ lấp đầy phòng hiện tại
    public function occupancyRate()
    {
        $totalRooms = Room::count();
        $occupiedRooms = Booking::whereDate('check_in_date', '<=', now())
            ->whereDate('check_out_date', '>', now())
            ->count();

        $rate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 2) : 0;

        return response()->json([
            'total_rooms' => $totalRooms,
            'occupied_rooms' => $occupiedRooms,
            'occupancy_rate' => $rate
        ]);
    }

    // 7. Trung bình thời gian lưu trú (ngày)
    public function averageStayDuration()
    {
        $average = DB::table('bookings')
            ->select(DB::raw('AVG(DATEDIFF(check_out_date, check_in_date)) as avg_days'))
            ->first();

        return response()->json(['average_stay_days' => round($average->avg_days, 2)]);
    }

    // 8. Tỷ lệ huỷ phòng
    public function cancellationRate()
    {
        $total = DB::table('bookings')->count();
        $cancelled = DB::table('bookings')->where('status', 'Canceled')->count();

        $rate = $total > 0 ? round(($cancelled / $total) * 100, 2) : 0;

        return response()->json([
            'total_bookings' => $total,
            'cancelled_bookings' => $cancelled,
            'cancellation_rate' => $rate
        ]);
    }

    // 9. Top khách hàng đặt nhiều nhất
    public function topFrequentCustomers()
    {
        $data = DB::table('bookings')
            ->join('customers', 'bookings.customer_id', '=', 'customers.id')
            ->select('customers.name', DB::raw('COUNT(bookings.id) as total_bookings'))
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_bookings')
            ->limit(5)
            ->get();

        return response()->json($data);
    }

    // 10. Tổng số booking theo tháng
    public function bookingsByMonth()
    {
        $data = DB::table('bookings')
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('COUNT(*) as total'))
            ->groupBy('month')
            ->orderBy('month', 'DESC')
            ->get();

        return response()->json($data);
    }
}
