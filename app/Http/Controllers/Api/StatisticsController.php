<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Room;

class StatisticsController extends Controller
{
    // 1. Tổng doanh thu toàn hệ thống
    public function totalRevenue()
    {
        $total = DB::table('bookings')
            ->where('status', 'Checked-out')
            ->sum('total_amount');

        return response()->json([
            'mess' => 'Tổng doanh thu toàn hệ thống',
            'data' => $total
        ]);
    }

    // 2. Doanh thu theo ngày
    public function revenueByDay()
    {
        $revenue = DB::table('bookings')
            ->where('status', 'Checked-out')
            ->select(DB::raw('DATE(check_out_date) as date'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('date')
            ->orderBy('date', 'DESC')
            ->get();

        return response()->json([
            'mess' => 'Lấy doanh thu theo ngày thành công',
            'data' => $revenue
        ]);
    }

    // 3. Tổng chi phí từng booking
    public function totalPerBooking()
    {
        $data = DB::table('bookings')
            ->where('status', 'Checked-out')
            ->select(
                'id as booking_id',
                DB::raw('IFNULL(total_amount, 0) as total_amount'),
                'check_in_date',
                'check_out_date'
            )
            ->orderBy('check_out_date', 'desc')
            ->get();

        return response()->json([
            'mess' => 'Lấy tổng chi phí từng booking thành công',
            'data' => $data
        ]);
    }

    // 4. Tổng doanh thu theo khách hàng
    public function revenueByCustomer()
    {
        $data = DB::table('bookings')
            ->join('customers', 'bookings.customer_id', '=', 'customers.id')
            ->where('bookings.status', 'Checked-out')
            ->select(
                'customers.name',
                DB::raw('SUM(COALESCE(bookings.total_amount, 0)) as total_spent')
            )
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        return response()->json([
            'mess' => 'Lấy doanh thu theo khách hàng thành công',
            'data' => $data
        ]);
    }

    // 5. Tổng doanh thu theo phòng
    public function revenueByRoom()
    {
        $data = DB::table('booking_room')
            ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
            ->join('rooms', 'booking_room.room_id', '=', 'rooms.id')
            ->join(
                DB::raw('(SELECT booking_id, COUNT(*) as room_count FROM booking_room GROUP BY booking_id) as br_count'),
                'booking_room.booking_id',
                '=',
                'br_count.booking_id'
            )
            ->where('bookings.status', 'Checked-out')
            ->select(
                'rooms.room_number',
                DB::raw('SUM(bookings.total_amount / br_count.room_count) as total_revenue')
            )
            ->groupBy('rooms.id', 'rooms.room_number')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->get();

        return response()->json([
            'mess' => 'Lấy doanh thu theo phòng thành công',
            'data' => $data
        ]);
    }

    // 6. Tỷ lệ lấp đầy phòng hiện tại
    public function occupancyRate()
    {
        $totalRooms = Room::count();

        $occupiedRooms = DB::table('booking_room')
            ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
            ->where('bookings.status', 'Checked-in')
            ->whereDate('bookings.check_in_date', '<=', now())
            ->whereDate('bookings.check_out_date', '>', now())
            ->count();

        $rate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 2) : 0;

        return response()->json([
            'mess' => 'Tỷ lệ lấp đầy phòng hiện tại',
            'total_rooms' => $totalRooms,
            'occupied_rooms' => $occupiedRooms,
            'occupancy_rate' => $rate
        ]);
    }

    // 7. Trung bình thời gian lưu trú (ngày)
    public function averageStayDuration()
    {
        $average = DB::table('bookings')
            ->where('status', 'Checked-out')
            ->select(DB::raw('AVG(DATEDIFF(check_out_date, check_in_date)) as avg_days'))
            ->first();

        return response()->json([
            'mess' => 'Tính trung bình thời gian lưu trú thành công',
            'average_stay_days' => round($average->avg_days ?? 0, 2)
        ]);
    }

    // 8. Tỷ lệ huỷ phòng
    public function cancellationRate()
    {
        $total = DB::table('bookings')
            ->whereIn('status', ['Checked-in', 'Checked-out', 'Canceled']) // chỉ tính các trạng thái có thật
            ->count();

        $cancelled = DB::table('bookings')
            ->whereRaw('LOWER(status) = ?', ['canceled']) // chống sai chính tả, viết hoa
            ->count();

        $rate = $total > 0 ? round(($cancelled / $total) * 100, 2) : 0;

        return response()->json([
            'mess' => 'Tính tỷ lệ huỷ phòng thành công',
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
            ->whereIn('bookings.status', ['Checked-in', 'Checked-out']) // lọc đơn hợp lệ
            ->select(
                'customers.id as customer_id',
                'customers.name',
                DB::raw('COUNT(bookings.id) as total_bookings')
            )
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_bookings')
            ->limit(5)
            ->get();

        return response()->json([
            'mess' => 'Lấy top khách hàng đặt nhiều nhất thành công',
            'data' => $data
        ]);
    }

    // 10. Tổng số booking theo tháng
    public function bookingsByMonth()
    {
        $data = DB::table('bookings')
            ->whereIn('status', ['Checked-in', 'Checked-out']) // chỉ tính đơn hợp lệ
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('month')
            ->orderBy('month', 'DESC')
            ->get();

        return response()->json([
            'mess' => 'Thống kê tổng số booking theo tháng thành công',
            'data' => $data
        ]);
    }

    // 11. Doanh thu theo loại phòng
    public function revenueByRoomType()
    {
        $data = DB::table('booking_room')
            ->join('rooms', 'booking_room.room_id', '=', 'rooms.id')
            ->join('room_types', 'rooms.room_type_id', '=', 'room_types.id')
            ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
            ->where('bookings.status', 'Checked-out')
            ->select('room_types.name as room_type', DB::raw('SUM(booking_room.rate) as total_revenue'))
            ->groupBy('room_types.id', 'room_types.name')
            ->orderByDesc('total_revenue')
            ->get();

        return response()->json([
            'message' => 'Doanh thu theo loại phòng',
            'data' => $data,
        ]);
    }

    // 12. Tổng doanh thu từ dịch vụ
    public function totalServiceRevenue()
    {
        $total = DB::table('booking_service')
            ->join('bookings', 'booking_service.booking_id', '=', 'bookings.id')
            ->join('services', 'booking_service.service_id', '=', 'services.id')
            ->where('bookings.status', 'Checked-out')
            ->select(DB::raw('SUM(booking_service.quantity * services.price) as total'))
            ->value('total');

        return response()->json([
            'message' => 'Tổng doanh thu dịch vụ',
            'total_service_revenue' => $total ?? 0,
        ]);
    }

    // 13. Số lượng phòng được đặt theo loại
    public function roomTypeBookingCount()
    {
        $data = DB::table('booking_room')
            ->join('rooms', 'booking_room.room_id', '=', 'rooms.id')
            ->join('room_types', 'rooms.room_type_id', '=', 'room_types.id')
            ->select('room_types.name as room_type', DB::raw('COUNT(booking_room.id) as total_booked'))
            ->groupBy('room_types.id', 'room_types.name')
            ->orderByDesc('total_booked')
            ->get();

        return response()->json([
            'message' => 'Số lượng phòng được đặt theo loại',
            'data' => $data,
        ]);
    }
}
