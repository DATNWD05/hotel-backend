<?php

namespace App\Http\Controllers\Api;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    // Hàm tiện ích dùng chung để lọc theo from_date, to_date
    protected function applyDateFilter($query, Request $request, $dateColumn = 'bookings.check_out_date')
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');

        if ($fromDate && $toDate) {
            $query->whereDate($dateColumn, '>=', $fromDate)
                ->whereDate($dateColumn, '<=', $toDate);
        } elseif ($fromDate) {
            $query->whereDate($dateColumn, '>=', $fromDate);
        } elseif ($toDate) {
            $query->whereDate($dateColumn, '<=', $toDate);
        }

        return $query;
    }

    // 1. Tổng doanh thu toàn hệ thống
    public function totalRevenue(Request $request)
    {
        // Doanh thu từ các booking đã checkout
        $checked = DB::table('bookings')->where('status', 'Checked-out');
        $checked = $this->applyDateFilter($checked, $request, 'bookings.check_out_date');
        $revChecked = (float) $checked->sum('total_amount');

        // Cọc bị phạt từ các booking hủy (Canceled có cọc)
        $forfeit = DB::table('bookings')
            ->where('status', 'Canceled')
            ->where('deposit_amount', '>', 0)
            ->where('bookings.is_deposit_paid', '=', 1);
        $forfeit = $this->applyDateFilter($forfeit, $request, 'bookings.created_at');
        $revForfeit = (float) $forfeit->sum('deposit_amount');

        return response()->json([
            'mess' => 'Tổng doanh thu toàn hệ thống (bao gồm cọc phạt)',
            'data' => $revChecked + $revForfeit,
            'breakdown' => [
                'checked_out' => $revChecked,
                'canceled_deposit' => $revForfeit,
            ],
        ]);
    }

    // 2. Doanh thu theo ngày
    public function revenueByDay(Request $request)
    {
        // 1) Doanh thu theo ngày checkout
        $q1 = DB::table('bookings')
            ->where('status', 'Checked-out')
            ->selectRaw("DATE(bookings.check_out_date) as date, SUM(bookings.total_amount) as total")
            ->groupBy(DB::raw('DATE(bookings.check_out_date)'));
        $q1 = $this->applyDateFilter($q1, $request, 'bookings.check_out_date');

        // 2) Cọc phạt theo ngày created_at
        $q2 = DB::table('bookings')
            ->where('status', 'Canceled')
            ->where('deposit_amount', '>', 0)
            ->where('bookings.is_deposit_paid', '=', 1)
            ->selectRaw("DATE(bookings.created_at) as date, SUM(bookings.deposit_amount) as total")
            ->groupBy(DB::raw('DATE(bookings.created_at)'));
        $q2 = $this->applyDateFilter($q2, $request, 'bookings.created_at');

        // UNION + cộng lại theo ngày
        $union = $q1->unionAll($q2);
        $rows = DB::table(DB::raw("({$union->toSql()}) as t"))
            ->mergeBindings($union)
            ->selectRaw('t.date, SUM(t.total) as total')
            ->groupBy('t.date')
            ->orderBy('t.date', 'DESC')
            ->get();

        return response()->json([
            'mess' => 'Lấy doanh thu theo ngày (cộng cọc phạt) thành công',
            'data' => $rows,
        ]);
    }

    // 3. Tổng chi phí từng booking
    public function totalPerBooking(Request $request)
    {
        // Booking hoàn tất
        $q1 = DB::table('bookings')
            ->where('status', 'Checked-out')
            ->selectRaw("
            bookings.id as booking_id,
            COALESCE(bookings.total_amount,0) as total_amount,
            bookings.check_in_date,
            bookings.check_out_date,
            DATE(bookings.check_out_date) as date_key,
            'Checked-out' as status
        ");
        $q1 = $this->applyDateFilter($q1, $request, 'bookings.check_out_date');

        // Booking hủy: total_amount = deposit_amount, date_key = created_at
        $q2 = DB::table('bookings')
            ->where('status', 'Canceled')
            ->where('deposit_amount', '>', 0)
            ->where('bookings.is_deposit_paid', '=', 1)
            ->selectRaw("
            bookings.id as booking_id,
            COALESCE(bookings.deposit_amount,0) as total_amount,
            bookings.check_in_date,
            bookings.check_out_date,
            DATE(bookings.created_at) as date_key,
            'Canceled' as status
        ");
        $q2 = $this->applyDateFilter($q2, $request, 'bookings.created_at');

        $union = $q1->unionAll($q2);
        $data = DB::table(DB::raw("({$union->toSql()}) as t"))
            ->mergeBindings($union)
            ->orderBy('t.date_key', 'desc')
            ->get();

        return response()->json([
            'mess' => 'Lấy tổng chi phí từng booking (kể cả cọc phạt) thành công',
            'data' => $data
        ]);
    }


    // 4. Doanh thu theo khách hàng
    public function revenueByCustomer(Request $request)
    {
        $limit = (int) $request->input('limit', 5);

        // Hoàn tất
        $q1 = DB::table('bookings')
            ->join('customers', 'bookings.customer_id', '=', 'customers.id')
            ->where('bookings.status', 'Checked-out')
            ->selectRaw("customers.id as cid, customers.name, SUM(COALESCE(bookings.total_amount,0)) as amt")
            ->groupBy('customers.id', 'customers.name');
        $q1 = $this->applyDateFilter($q1, $request, 'bookings.check_out_date');

        // Cọc phạt của hủy
        $q2 = DB::table('bookings')
            ->join('customers', 'bookings.customer_id', '=', 'customers.id')
            ->where('bookings.status', 'Canceled')
            ->where('bookings.deposit_amount', '>', 0)
            ->where('bookings.is_deposit_paid', '=', 1)
            ->selectRaw("customers.id as cid, customers.name, SUM(COALESCE(bookings.deposit_amount,0)) as amt")
            ->groupBy('customers.id', 'customers.name');
        $q2 = $this->applyDateFilter($q2, $request, 'bookings.created_at');

        $union = $q1->unionAll($q2);
        $rows = DB::table(DB::raw("({$union->toSql()}) as u"))
            ->mergeBindings($union)
            ->selectRaw('u.cid, u.name, SUM(u.amt) as total_spent')
            ->groupBy('u.cid', 'u.name')
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get();

        return response()->json([
            'mess' => 'Lấy doanh thu theo khách hàng (kể cả cọc phạt) thành công',
            'data' => $rows
        ]);
    }

    // 5. Doanh thu theo phòng (room-only: rate × nights (+ tiện nghi phòng nếu có))
    public function revenueByRoom(Request $request)
    {
        // Subquery cộng tiện nghi theo phòng (nếu bạn muốn gộp vào doanh thu phòng)
        $amenitiesSub = DB::table('booking_room_amenities')
            ->select('booking_id', 'room_id', DB::raw('SUM(price * quantity) as amenities_total'))
            ->groupBy('booking_id', 'room_id');

        $query = DB::table('booking_room')
            ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
            ->join('rooms', 'booking_room.room_id', '=', 'rooms.id')
            ->leftJoinSub($amenitiesSub, 'bra', function ($join) {
                $join->on('bra.booking_id', '=', 'booking_room.booking_id')
                    ->on('bra.room_id', '=', 'booking_room.room_id');
            })
            ->where('bookings.status', 'Checked-out')
            ->where('bookings.is_hourly', 0)
            ->selectRaw("
            rooms.room_number,
            SUM(
              COALESCE(booking_room.rate,0) *
              GREATEST(1, DATEDIFF(DATE(bookings.check_out_date), DATE(bookings.check_in_date)))
              + COALESCE(bra.amenities_total, 0)
            ) as total_revenue
        ")
            ->groupBy('rooms.id', 'rooms.room_number')
            ->orderByDesc('total_revenue');

        $query = $this->applyDateFilter($query, $request, 'bookings.check_out_date');

        $data = $query->limit(5)->get();

        return response()->json([
            'mess' => 'Lấy doanh thu theo phòng (room revenue)',
            'data' => $data
        ]);
    }

    // 6. Doanh thu theo loại phòng (rate × số đêm; loại booking theo giờ)
    public function revenueByRoomType(Request $request)
    {
        $query = DB::table('booking_room')
            ->join('rooms', 'booking_room.room_id', '=', 'rooms.id')
            ->join('room_types', 'rooms.room_type_id', '=', 'room_types.id')
            ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
            ->where('bookings.status', 'Checked-out')
            ->where('bookings.is_hourly', 0)
            ->selectRaw("
            room_types.name as room_type,
            SUM(
              COALESCE(booking_room.rate, 0) *
              GREATEST(1, DATEDIFF(DATE(bookings.check_out_date), DATE(bookings.check_in_date)))
            ) as total_revenue
        ")
            ->groupBy('room_types.id', 'room_types.name')
            ->orderByDesc('total_revenue');

        $query = $this->applyDateFilter($query, $request, 'bookings.check_out_date');

        return response()->json([
            'message' => 'Doanh thu theo loại phòng',
            'data' => $query->get()
        ]);
    }

    // 7. Tổng doanh thu từ dịch vụ
    public function totalServiceRevenue(Request $request)
    {
        $query = DB::table('booking_service')
            ->join('bookings', 'booking_service.booking_id', '=', 'bookings.id')
            ->join('services', 'booking_service.service_id', '=', 'services.id')
            ->where('bookings.status', 'Checked-out')
            ->select(DB::raw('SUM(COALESCE(booking_service.quantity, 0) * COALESCE(services.price, 0)) as total'));

        $query = $this->applyDateFilter($query, $request, 'booking_service.created_at');

        $total = $query->value('total');

        return response()->json([
            'message' => 'Tổng doanh thu dịch vụ',
            'total_service_revenue' => ($total ?? 0)
        ]);
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

        $query = $this->applyDateFilter($query, $request, 'bookings.created_at');

        $data = $query->get();

        return response()->json([
            'message' => 'Số lượng phòng được đặt theo loại',
            'data' => $data
        ]);
    }

    // 9. Top khách hàng đặt nhiều nhất
    public function topFrequentCustomers(Request $request)
    {
        $query = DB::table('bookings')
            ->join('customers', 'bookings.customer_id', '=', 'customers.id')
            ->whereIn('bookings.status', ['Checked-in', 'Checked-out'])
            ->select(
                'customers.id as customer_id',
                'customers.name',
                DB::raw('COUNT(bookings.id) as total_bookings')
            )
            ->groupBy('customers.id', 'customers.name')
            ->orderByDesc('total_bookings');

        $query = $this->applyDateFilter($query, $request, 'bookings.created_at');

        $data = $query->limit(5)->get();

        return response()->json([
            'mess' => 'Lấy top khách hàng đặt nhiều nhất thành công',
            'data' => $data
        ]);
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

        $fromDate = $request->input('from_date') ?? now()->toDateString();
        $toDate = $request->input('to_date') ?? now()->toDateString();

        $occupiedRoomsQuery = DB::table('booking_room')
            ->join('bookings', 'booking_room.booking_id', '=', 'bookings.id')
            ->whereIn('bookings.status', ['Checked-in', 'Checked-out'])
            ->whereDate('bookings.check_in_date', '<=', $toDate)
            ->whereDate('bookings.check_out_date', '>=', $fromDate);

        $occupiedRooms = $occupiedRoomsQuery->count();

        $rate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100, 2) : 0;

        return response()->json([
            'mess' => 'Tỷ lệ lấp đầy phòng',
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'total_rooms' => $totalRooms,
            'occupied_rooms' => $occupiedRooms,
            'occupancy_rate' => $rate
        ]);
    }

    // 12a. Trung bình thời gian lưu trú - ĐẶT THEO NGÀY (is_hourly=0)
    public function averageStayDuration(Request $request)
    {
        $q = DB::table('bookings')
            ->where('status', 'Checked-out')
            ->where('is_hourly', 0)
            ->whereNotNull('check_in_date')
            ->whereNotNull('check_out_date')
            // nights = số đêm; đảm bảo không âm
            ->whereRaw('DATEDIFF(DATE(check_out_date), DATE(check_in_date)) >= 0');

        // lọc theo ngày trả phòng (date)
        $q = $this->applyDateFilter($q, $request, 'bookings.check_out_date');

        $row = $q->selectRaw("
        AVG(DATEDIFF(DATE(check_out_date), DATE(check_in_date)))  AS avg_nights,
        AVG(GREATEST(1, DATEDIFF(DATE(check_out_date), DATE(check_in_date)))) AS avg_billable_nights,
        SUM(DATEDIFF(DATE(check_out_date), DATE(check_in_date)))  AS total_nights,
        COUNT(*)                                                  AS count_bookings
    ")->first();

        return response()->json([
            'mess' => 'Trung bình thời gian lưu trú - đặt theo ngày',
            'from_date' => $request->input('from_date'),
            'to_date'   => $request->input('to_date'),
            // trung bình đúng nghĩa số đêm (có thể =0 nếu nhận/trả cùng ngày)
            'avg_nights'          => round((float)($row->avg_nights ?? 0), 2),
            // trung bình “tính tiền tối thiểu 1 đêm”
            'average_stay_days' => round((float)($row->avg_billable_nights ?? 0), 2),
            'total_nights'        => (int)($row->total_nights ?? 0),
            'count_bookings'      => (int)($row->count_bookings ?? 0),
        ]);
    }

    // 12b. Trung bình thời gian lưu trú - ĐẶT THEO GIỜ (is_hourly=1)
    public function averageStayHourly(Request $request)
    {
        $q = DB::table('bookings')
            ->where('status', 'Checked-out')
            ->where('is_hourly', 1)
            ->whereNotNull('check_in_at')
            ->whereNotNull('check_out_at')
            // đảm bảo không âm
            ->whereRaw('TIMESTAMPDIFF(SECOND, check_in_at, check_out_at) >= 0');

        // lọc theo thời điểm trả phòng (datetime)
        $q = $this->applyDateFilter($q, $request, 'bookings.check_out_at');

        // dùng phút để chính xác hơn, rồi đổi ra giờ
        $row = $q->selectRaw("
        AVG(TIMESTAMPDIFF(MINUTE, check_in_at, check_out_at)) / 60 AS avg_hours,
        SUM(TIMESTAMPDIFF(MINUTE, check_in_at, check_out_at)) / 60 AS total_hours,
        COUNT(*)                                                    AS count_bookings
    ")->first();

        return response()->json([
            'mess' => 'Trung bình thời gian lưu trú - đặt theo giờ',
            'from_date' => $request->input('from_date'),
            'to_date'   => $request->input('to_date'),
            'avg_hours'       => round((float)($row->avg_hours ?? 0), 2),
            'total_hours'     => round((float)($row->total_hours ?? 0), 2),
            'count_bookings'  => (int)($row->count_bookings ?? 0),
        ]);
    }

    // 13. Tỷ lệ huỷ phòng (lọc theo created_at để đúng ngữ nghĩa)
    public function cancellationRate(Request $request)
    {
        // Tổng các đơn trong kỳ (theo thiết kế cũ: chỉ tính 3 trạng thái này)
        $queryTotal = DB::table('bookings')
            ->whereIn('status', ['Checked-in', 'Checked-out', 'Canceled']);

        // Các đơn bị huỷ
        $queryCancel = DB::table('bookings')
            ->whereRaw('LOWER(status) = ?', ['canceled']);

        // Lọc theo ngày tạo đơn (không dùng check_out_date)
        $queryTotal  = $this->applyDateFilter($queryTotal,  $request, 'bookings.created_at');
        $queryCancel = $this->applyDateFilter($queryCancel, $request, 'bookings.created_at');

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

    // 14. Bảng doanh thu
    public function revenueTable(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $page    = (int) $request->input('page', 1);

        // Gộp danh sách phòng & loại phòng theo từng booking để tránh lặp dòng
        $roomsAgg = DB::table('booking_room')
            ->join('rooms', 'booking_room.room_id', '=', 'rooms.id')
            ->join('room_types', 'rooms.room_type_id', '=', 'room_types.id')
            ->select(
                'booking_room.booking_id',
                DB::raw("GROUP_CONCAT(DISTINCT rooms.room_number ORDER BY rooms.room_number SEPARATOR ', ') AS room_numbers"),
                DB::raw("GROUP_CONCAT(DISTINCT room_types.name ORDER BY room_types.name SEPARATOR ', ') AS room_types")
            )
            ->groupBy('booking_room.booking_id');

        // Điều kiện trạng thái:
        // - Lấy Checked-in / Checked-out / Pending
        // - Lấy Canceled CHỈ KHI có deposit_amount > 0
        $statusFilter = function ($q) {
            $q->whereIn('bookings.status', ['Checked-in', 'Checked-out', 'Pending'])
                ->orWhere(function ($q2) {
                    $q2->where('bookings.status', 'Canceled')
                        ->where('deposit_amount', '>', 0)
                        ->where('bookings.is_deposit_paid', '=', 1);
                });
        };

        // BẢNG: 1 dòng / booking
        $query = DB::table('bookings')
            ->join('customers', 'bookings.customer_id', '=', 'customers.id')
            ->leftJoinSub($roomsAgg, 'ra', function ($join) {
                $join->on('ra.booking_id', '=', 'bookings.id');
            })
            ->where($statusFilter)
            ->select(
                'bookings.id as booking_code',
                'customers.name as customer_name',
                DB::raw('COALESCE(ra.room_types, "") as room_type'),
                DB::raw('COALESCE(ra.room_numbers, "") as room_number'),
                'bookings.check_in_date',
                'bookings.check_out_date',
                'bookings.total_amount',
                'bookings.deposit_amount',
                // Canceled thì còn lại = 0, các trạng thái khác = total - deposit
                DB::raw("CASE WHEN bookings.status = 'Canceled' THEN 0
                          ELSE (bookings.total_amount - bookings.deposit_amount) END as remaining_amount"),
                'bookings.status'
            )
            ->orderBy('bookings.created_at', 'desc');

        // Lọc ngày: giữ theo created_at để cả đơn hủy (không có check_out_date) vẫn lọc được
        $query = $this->applyDateFilter($query, $request, 'bookings.created_at');

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        // SUMMARY: dùng cùng điều kiện trạng thái + cùng cột ngày
        $base = DB::table('bookings')->where($statusFilter);
        $base = $this->applyDateFilter($base, $request, 'bookings.created_at');

        // Nhóm "không hủy"
        $normal = (clone $base)
            ->whereIn('status', ['Checked-in', 'Checked-out', 'Pending'])
            ->selectRaw('
            SUM(total_amount) as tot,
            SUM(deposit_amount) as dep,
            SUM(total_amount - deposit_amount) as rem
        ')
            ->first();

        // Nhóm "Canceled có cọc" (cọc bị phạt tính như doanh thu)
        $forfeit = (clone $base)
            ->where('status', 'Canceled')
            ->where('deposit_amount', '>', 0)
            ->where('bookings.is_deposit_paid', '=', 1)
            ->selectRaw('SUM(deposit_amount) as forfeited_dep')
            ->first();

        $totNormal   = (float) ($normal->tot ?? 0);
        $depNormal   = (float) ($normal->dep ?? 0);
        $remNormal   = (float) ($normal->rem ?? 0);
        $depForfeit  = (float) ($forfeit->forfeited_dep ?? 0);

        // Quy tắc tổng kết:
        // - Tổng doanh thu = doanh thu booking thường + cọc bị phạt của đơn hủy
        // - Đặt cọc = tổng cọc của tất cả booking (kể cả hủy)
        // - Còn lại = chỉ tính với booking thường (đơn hủy = 0)
        $summaryTotal     = $totNormal + $depForfeit;
        $summaryDeposit   = $depNormal + $depForfeit;
        $summaryRemaining = $remNormal; // canceled = 0

        return response()->json([
            'message' => 'Dữ liệu bảng doanh thu (kể cả Canceled có cọc)',
            'data' => $paginated->items(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'per_page'     => $paginated->perPage(),
                'total'        => $paginated->total()
            ],
            'summary' => [
                'total_amount'    => $summaryTotal,
                'deposit_amount'  => $summaryDeposit,
                'remaining_amount' => $summaryRemaining
            ]
        ]);
    }

    // 15. Bảng dịch vụ đã sử dụng (group theo service_id; đếm tổng nhóm không tải toàn bộ)
    public function bookingServiceTable(Request $request)
    {
        $perPage = (int) $request->input('per_page', 10);
        $page    = (int) $request->input('page', 1);

        // Base + filter ngày theo created_at của booking_service
        $base = DB::table('booking_service')
            ->join('bookings', 'booking_service.booking_id', '=', 'bookings.id')
            ->join('services', 'booking_service.service_id', '=', 'services.id')
            ->join('service_categories', 'services.category_id', '=', 'service_categories.id')
            ->where('bookings.status', 'Checked-out');

        $base = $this->applyDateFilter($base, $request, 'booking_service.created_at');

        // Query đã GROUP (ổn định theo service_id)
        $grouped = (clone $base)
            ->selectRaw("
            booking_service.service_id,
            services.name as service_name,
            service_categories.name as category_name,
            services.price as price_snapshot,
            SUM(booking_service.quantity) as total_quantity,
            SUM(booking_service.quantity * COALESCE(services.price,0)) as total
        ")
            ->groupBy('booking_service.service_id', 'services.name', 'service_categories.name', 'services.price')
            ->orderByDesc('total');

        // Tổng số nhóm (không load toàn bộ)
        $total = DB::table(DB::raw("({$grouped->toSql()}) as t"))
            ->mergeBindings($grouped)
            ->count();

        // Phân trang
        $data = DB::table(DB::raw("({$grouped->toSql()}) as t"))
            ->mergeBindings($grouped)
            ->forPage($page, $perPage)
            ->get();

        // Tổng tiền dịch vụ (theo schema hiện tại vẫn phải nhân với services.price)
        $summary = (clone $base)
            ->selectRaw('SUM(booking_service.quantity * COALESCE(services.price,0)) as total_amount')
            ->value('total_amount');

        return response()->json([
            'message' => 'Dữ liệu bảng dịch vụ',
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => (int) $total,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'summary' => [
                'total_amount' => (float) ($summary ?? 0),
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
            'average_stay_hourly'  => $this->averageStayHourly($request)->getData(),
            'cancellation_rate' => $this->cancellationRate($request)->getData(),
            'top_customers' => $this->topFrequentCustomers($request)->getData(),
            'bookings_by_month' => $this->bookingsByMonth($request)->getData(),
            'total_per_booking' => $this->totalPerBooking($request)->getData(),
        ]]);
    }
}
