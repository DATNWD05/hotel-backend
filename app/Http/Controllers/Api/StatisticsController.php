<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Room;

class StatisticsController extends Controller
{
    // Hàm tiện ích dùng chung để lọc theo from_date, to_date
    protected function applyDateFilter($query, Request $request)
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        if ($fromDate && $toDate) {
            $query->whereDate('check_out_date', '>=', $fromDate)
                ->whereDate('check_in_date', '<=', $toDate);
        } elseif ($fromDate) {
            $query->whereDate('check_out_date', '>=', $fromDate);
        } elseif ($toDate) {
            $query->whereDate('check_in_date', '<=', $toDate);
        }

        return $query;
    }

    // 1. Tổng doanh thu toàn hệ thống
    public function totalRevenue(Request $request)
    {
        $query = DB::table('bookings')->where('status', 'Checked-out');
        $query = $this->applyDateFilter($query, $request);
        $total = $query->sum('total_amount');

        return response()->json(['mess' => 'Tổng doanh thu toàn hệ thống', 'data' => $total]);
    }

    // 2. Doanh thu theo ngày
    public function revenueByDay(Request $request)
    {
        $query = DB::table('bookings')
            ->where('status', 'Checked-out')
            ->select(DB::raw('DATE(check_out_date) as date'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('date')
            ->orderBy('date', 'DESC');

        $query = $this->applyDateFilter($query, $request);
        $revenue = $query->get();

        return response()->json(['mess' => 'Lấy doanh thu theo ngày thành công', 'data' => $revenue]);
    }

    // 3. Tổng chi phí từng booking
    public function totalPerBooking(Request $request)
    {
        $query = DB::table('bookings')
            ->where('status', 'Checked-out')
            ->select('id as booking_id', DB::raw('IFNULL(total_amount, 0) as total_amount'), 'check_in_date', 'check_out_date')
            ->orderBy('check_out_date', 'desc');

        $query = $this->applyDateFilter($query, $request);
        $data = $query->get();

        return response()->json(['mess' => 'Lấy tổng chi phí từng booking thành công', 'data' => $data]);
    }

    // 4. Doanh thu theo khách hàng
    public function revenueByCustomer(Request $request)
    {
        $query = DB::table('bookings')
            ->join('customers', 'bookings.customer_id', '=', 'customers.id')
            ->where('bookings.status', 'Checked-out')
            ->select('customers.name', DB::raw('SUM(COALESCE(bookings.total_amount, 0)) as total_spent'))
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_spent');

        $query = $this->applyDateFilter($query, $request);
        $data = $query->limit(5)->get();

        return response()->json(['mess' => 'Lấy doanh thu theo khách hàng thành công', 'data' => $data]);
    }

    // 5. Doanh thu theo phòng
    public function revenueByRoom(Request $request)
    {
        $query = DB::table('booking_room')
            ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
            ->join('rooms', 'booking_room.room_id', '=', 'rooms.id')
            ->join(DB::raw('(SELECT booking_id, COUNT(*) as room_count FROM booking_room GROUP BY booking_id) as br_count'), 'booking_room.booking_id', '=', 'br_count.booking_id')
            ->where('bookings.status', 'Checked-out')
            ->select('rooms.room_number', DB::raw('SUM(bookings.total_amount / br_count.room_count) as total_revenue'))
            ->groupBy('rooms.id', 'rooms.room_number')
            ->orderByDesc('total_revenue');

        $query = $this->applyDateFilter($query, $request);
        $data = $query->limit(5)->get();

        return response()->json(['mess' => 'Lấy doanh thu theo phòng thành công', 'data' => $data]);
    }

    // 6. Doanh thu theo loại phòng
    public function revenueByRoomType(Request $request)
    {
        $query = DB::table('booking_room')
            ->join('rooms', 'booking_room.room_id', '=', 'rooms.id')
            ->join('room_types', 'rooms.room_type_id', '=', 'room_types.id')
            ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
            ->where('bookings.status', 'Checked-out')
            ->select('room_types.name as room_type', DB::raw('SUM(booking_room.rate) as total_revenue'))
            ->groupBy('room_types.id', 'room_types.name')
            ->orderByDesc('total_revenue');

        $query = $this->applyDateFilter($query, $request);
        $data = $query->get();

        return response()->json(['message' => 'Doanh thu theo loại phòng', 'data' => $data]);
    }

    // 7. Tổng doanh thu từ dịch vụ
    public function totalServiceRevenue(Request $request)
    {
        $query = DB::table('booking_service')
            ->join('bookings', 'booking_service.booking_id', '=', 'bookings.id')
            ->join('services', 'booking_service.service_id', '=', 'services.id')
            ->where('bookings.status', 'Checked-out')
            ->select(DB::raw('SUM(booking_service.quantity * services.price) as total'));

        $query = $this->applyDateFilter($query, $request);
        $total = $query->value('total');

        return response()->json(['message' => 'Tổng doanh thu dịch vụ', 'total_service_revenue' => $total ?? 0]);
    }

    // 8. Số lượng phòng được đặt theo loại
    public function roomTypeBookingCount(Request $request)
    {
        $query = DB::table('booking_room')
            ->join('rooms', 'booking_room.room_id', '=', 'rooms.id')
            ->join('room_types', 'rooms.room_type_id', '=', 'room_types.id')
            ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.status', ['Checked-in', 'Checked-out'])
            ->select('room_types.name as room_type', DB::raw('COUNT(booking_room.id) as total_booked'))
            ->groupBy('room_types.id', 'room_types.name')
            ->orderByDesc('total_booked');

        $query = $this->applyDateFilter($query, $request);
        $data = $query->get();

        return response()->json(['message' => 'Số lượng phòng được đặt theo loại', 'data' => $data]);
    }

    // 9. Top khách hàng đặt nhiều nhất
    public function topFrequentCustomers(Request $request)
    {
        $query = DB::table('bookings')
            ->join('customers', 'bookings.customer_id', '=', 'customers.id')
            ->whereIn('bookings.status', ['Checked-in', 'Checked-out'])
            ->select('customers.id as customer_id', 'customers.name', DB::raw('COUNT(bookings.id) as total_bookings'))
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_bookings');

        $query = $this->applyDateFilter($query, $request, 'bookings.check_in_date');
        $data = $query->limit(5)->get();

        return response()->json(['mess' => 'Lấy top khách hàng đặt nhiều nhất thành công', 'data' => $data]);
    }

    // 10. Tổng số booking theo tháng
    public function bookingsByMonth(Request $request)
    {
        $query = DB::table('bookings')
            ->whereIn('status', ['Checked-in', 'Checked-out'])
            ->select(DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"), DB::raw('COUNT(*) as total'))
            ->groupBy('month')
            ->orderBy('month', 'DESC');

        $query = $this->applyDateFilter($query, $request, 'created_at');
        $data = $query->get();

        return response()->json(['mess' => 'Thống kê tổng số booking theo tháng thành công', 'data' => $data]);
    }

    // 11. Tỷ lệ lấp đầy phòng
    public function occupancyRate(Request $request)
    {
        $totalRooms = Room::count();

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        $occupiedRoomsQuery = DB::table('booking_room')
            ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
            ->where('bookings.status', 'Checked-in');

        if ($fromDate && $toDate) {
            // Trường hợp có chọn khoảng thời gian
            $occupiedRoomsQuery->whereDate('bookings.check_in_date', '<=', $toDate)
                ->whereDate('bookings.check_out_date', '>=', $fromDate);
        } else {
            // Mặc định: tính tại thời điểm hiện tại
            $occupiedRoomsQuery->whereDate('bookings.check_in_date', '<=', now())
                ->whereDate('bookings.check_out_date', '>', now());
        }

        $occupiedRooms = $occupiedRoomsQuery->count();

        $rate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 2) : 0;

        return response()->json([
            'mess' => 'Tỷ lệ lấp đầy phòng',
            'total_rooms' => $totalRooms,
            'occupied_rooms' => $occupiedRooms,
            'occupancy_rate' => $rate
        ]);
    }

    // 12. Trung bình thời gian lưu trú
    public function averageStayDuration(Request $request)
    {
        $query = DB::table('bookings')->where('status', 'Checked-out');
        $query = $this->applyDateFilter($query, $request);

        $average = $query->select(DB::raw('AVG(DATEDIFF(check_out_date, check_in_date)) as avg_days'))->first();

        return response()->json([
            'mess' => 'Tính trung bình thời gian lưu trú thành công',
            'average_stay_days' => round($average->avg_days ?? 0, 2)
        ]);
    }

    // 13. Tỷ lệ huỷ phòng
    public function cancellationRate(Request $request)
    {
        $queryTotal = DB::table('bookings')
            ->whereIn('status', ['Checked-in', 'Checked-out', 'Canceled']);

        $queryCancel = DB::table('bookings')
            ->whereRaw('LOWER(status) = ?', ['canceled']);

        $queryTotal = $this->applyDateFilter($queryTotal, $request);
        $queryCancel = $this->applyDateFilter($queryCancel, $request);

        $total = $queryTotal->count();
        $cancelled = $queryCancel->count();

        $rate = $total > 0 ? round(($cancelled / $total) * 100, 2) : 0;

        return response()->json([
            'mess' => 'Tính tỷ lệ huỷ phòng thành công',
            'total_bookings' => $total,
            'cancelled_bookings' => $cancelled,
            'cancellation_rate' => $rate
        ]);
    }

    // 14. Bảng doanh thu chi tiết theo booking
    public function revenueTable(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $query = DB::table('bookings')
            ->join('customers', 'bookings.customer_id', '=', 'customers.id')
            ->join('booking_room', 'bookings.id', '=', 'booking_room.booking_id')
            ->join('rooms', 'booking_room.room_id', '=', 'rooms.id')
            ->join('room_types', 'rooms.room_type_id', '=', 'room_types.id')
            ->select(
                'bookings.id as booking_code',
                'customers.name as customer_name',
                'room_types.name as room_type',
                'rooms.room_number',
                'bookings.check_in_date',
                'bookings.check_out_date',
                'bookings.total_amount',
                'bookings.deposit_amount',
                DB::raw('(bookings.total_amount - bookings.deposit_amount) as remaining_amount'),
                'bookings.status'
            )
            ->orderBy('bookings.created_at', 'desc');

        $query = $this->applyDateFilter($query, $request, 'bookings.created_at');
        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        $summary = DB::table('bookings')
            ->whereIn('status', ['Checked-in', 'Checked-out', 'Pending']);
        $summary = $this->applyDateFilter($summary, $request);

        $summaryData = $summary->selectRaw('
            SUM(total_amount) as total_amount,
            SUM(deposit_amount) as deposit_amount,
            SUM(total_amount - deposit_amount) as remaining_amount
        ')->first();

        return response()->json([
            'message' => 'Dữ liệu bảng doanh thu',
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total()
            ],
            'summary' => [
                'total_amount' => (int) $summaryData->total_amount,
                'deposit_amount' => (int) $summaryData->deposit_amount,
                'remaining_amount' => (int) $summaryData->remaining_amount
            ]
        ]);
    }

    // 15. Bảng dịch vụ đã sử dụng
    public function bookingServiceTable(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $query = DB::table('booking_service')
            ->join('bookings', 'booking_service.booking_id', '=', 'bookings.id')
            ->join('booking_room', 'bookings.id', '=', 'booking_room.booking_id')
            ->join('rooms', 'booking_room.room_id', '=', 'rooms.id')
            ->join('room_types', 'rooms.room_type_id', '=', 'room_types.id')
            ->join('services', 'booking_service.service_id', '=', 'services.id')
            ->join('service_categories', 'services.category_id', '=', 'service_categories.id')
            ->join('employees', 'bookings.created_by', '=', 'employees.id')
            ->select(
                'bookings.id as booking_code',
                'services.name as service_name',
                'service_categories.name as category_name',
                'services.price',
                'booking_service.quantity',
                DB::raw('(booking_service.quantity * services.price) as total'),
                'employees.name as employee_name',
                'rooms.room_number',
                'room_types.name as room_type',
                'booking_service.created_at'
            )
            ->orderBy('booking_service.created_at', 'desc');

        $query = $this->applyDateFilter($query, $request, 'booking_service.created_at');

        $total = $query->count();
        $data = $query->forPage($page, $perPage)->get();

        $summary = DB::table('booking_service')
            ->join('services', 'booking_service.service_id', '=', 'services.id');
        $summary = $this->applyDateFilter($summary, $request, 'booking_service.created_at');

        $summaryData = $summary->select(DB::raw('SUM(booking_service.quantity * services.price) as total_amount'))->first();

        return response()->json([
            'message' => 'Dữ liệu bảng dịch vụ',
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
            'summary' => [
                'total_amount' => $summaryData->total_amount ?? 0,
            ]
        ]);
    }

    // 16. Trang tổng hợp dashboard
    public function summaryDashboard(Request $request)
    {
        return response()->json(['mess' => 'Tổng hợp dữ liệu thống kê cho dashboard thành công', 'data' => [
            'total_revenue' => $this->totalRevenue($request)->getData(),
            'revenue_by_day' => $this->revenueByDay($request)->getData(),
            'revenue_by_room' => $this->revenueByRoom($request)->getData(),
            'revenue_by_customer' => $this->revenueByCustomer($request)->getData(),
            'revenue_by_room_type' => $this->revenueByRoomType($request)->getData(),
            'room_type_booking_count' => $this->roomTypeBookingCount($request)->getData(),
            'total_service_revenue' => $this->totalServiceRevenue($request)->getData(),
            'occupancy_rate' => $this->occupancyRate($request)->getData(),
            'average_stay_duration' => $this->averageStayDuration($request)->getData(),
            'cancellation_rate' => $this->cancellationRate($request)->getData(),
            'top_customers' => $this->topFrequentCustomers($request)->getData(),
            'bookings_by_month' => $this->bookingsByMonth($request)->getData(),
            'total_per_booking' => $this->totalPerBooking($request)->getData(),
        ]]);
    }
}
