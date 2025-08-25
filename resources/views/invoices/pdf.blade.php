@php
use Carbon\Carbon;

/**
* Helper: format tiền
*/
if (!function_exists('vnd')) {
function vnd($n) {
try { return number_format((float)$n, 0, ',', '.') . ' đ'; }
catch (\Throwable $e) { return '0 đ'; }
}
}

/**
* Phát hiện có payload mới hay không
*/
$hasPayload = isset($payload) && is_array($payload);

/**
* Lấy meta giờ/đêm + ngày giờ hiển thị
*/
if ($hasPayload) {
$isHourly = (int)($payload['meta']['is_hourly'] ?? 0) === 1;
$durationLabel = $payload['meta']['duration_label'] ?? ($isHourly ? 'hours' : 'nights');
$durationValue = (int)($payload['meta']['duration_value'] ?? 0);
$fmtCheckin = $payload['meta']['formatted_checkin'] ?? null;
$fmtCheckout = $payload['meta']['formatted_checkout'] ?? null;
$fmtIssued = $payload['meta']['formatted_issued'] ?? null;

$roomLines = $payload['room_lines'] ?? [];
$serviceLines = $payload['service_lines'] ?? [];
$amenityLines = $payload['amenity_lines'] ?? [];

$saved = $payload['totals']['saved'] ?? [];
$roomAmountSaved = $saved['room_amount'] ?? 0;
$serviceAmountSaved = $saved['service_amount'] ?? 0;
$amenityAmountSaved = $saved['amenity_amount'] ?? 0;
$discountSaved = $saved['discount_amount'] ?? 0;
$depositSaved = $saved['deposit_amount'] ?? 0;
$finalAmountSaved = $saved['final_amount'] ?? 0;

$invoiceCode = $payload['invoice']['invoice_code'] ?? ($invoice->invoice_code ?? '');
$issuedDate = $fmtIssued ?: (optional($invoice->issued_date)->format('d/m/Y') ?? '');
$customerName = $payload['booking']['customer']['name'] ?? ($invoice->booking->customer->name ?? '---');
$customerEmail = $payload['booking']['customer']['email'] ?? ($invoice->booking->customer->email ?? '---');
$customerPhone = $payload['booking']['customer']['phone'] ?? ($invoice->booking->customer->phone ?? '---');
} else {
// Fallback: controller cũ
$isHourly = (int)($invoice->booking->is_hourly ?? 0) === 1;
$durationLabel = $isHourly ? 'hours' : 'nights';

$fmtCheckin = optional($invoice->booking->check_in_date)->format('d/m/Y');
$fmtCheckout = optional($invoice->booking->check_out_date)->format('d/m/Y');
$issuedDate = optional($invoice->created_at)->format('d/m/Y');

// Từ controller cũ, room đã được gắn $room->nights và $room->room_total (theo ngày)
// Nếu là theo giờ thì controller cũ không có — nên sẽ chỉ hiển thị theo đêm.
$roomLines = collect($invoice->booking->rooms ?? [])->map(function($r) {
return [
'room_id' => $r->id ?? null,
'room_number' => $r->room_number ?? '',
'unit' => 'night',
'unit_count' => (int)($r->nights ?? 0),
'base_rate' => (float)($r->roomType->base_rate ?? 0),
'total' => (float)($r->room_total ?? 0),
'room_type' => $r->roomType->name ?? '---',
];
})->toArray();

$serviceLines = collect($invoice->booking->services ?? [])->map(function($s) {
$qty = (int)($s->pivot->quantity ?? 0);
$price = (float)($s->pivot->price ?? $s->price ?? 0);
return [
'service_id' => $s->id ?? null,
'name' => $s->name ?? '',
'price' => $price,
'quantity' => $qty,
'total' => $price * $qty,
];
})->toArray();

// Controller cũ chưa truyền tiện nghi phát sinh → để mảng rỗng
$amenityLines = [];

// Totals: dùng số đã lưu trong invoice (ưu tiên “chốt sổ”)
$roomAmountSaved = (float)($invoice->room_amount ?? 0);
$serviceAmountSaved = (float)($invoice->service_amount ?? 0);
$amenityAmountSaved = (float)($invoice->amenity_amount ?? 0); // cột mới nếu có
$discountSaved = (float)($invoice->discount_amount ?? 0);
$depositSaved = (float)($invoice->deposit_amount ?? 0);
$finalAmountSaved = (float)($invoice->total_amount ?? ($roomAmountSaved + $serviceAmountSaved + $amenityAmountSaved - $discountSaved - $depositSaved));

$invoiceCode = $invoice->invoice_code ?? '';
$customerName = $invoice->booking->customer->name ?? '---';
$customerEmail = $invoice->booking->customer->email ?? '---';
$customerPhone = $invoice->booking->customer->phone ?? '---';
}
@endphp

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8" />
    <title>Hóa đơn {{ $invoiceCode }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 14px;
            color: #333;
            padding: 40px;
        }

        .brand {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            color: #007BFF;
        }

        .sub-brand {
            text-align: center;
            font-size: 13px;
            color: #666;
            margin-bottom: 20px;
        }

        h2 {
            text-align: center;
            margin: 0 0 20px;
            font-size: 18px;
            text-transform: uppercase;
        }

        .invoice-info {
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 25px;
            background: #f9f9f9;
            border-radius: 5px;
        }

        .invoice-info p {
            margin: 6px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th,
        td {
            padding: 8px 10px;
            border: 1px solid #ddd;
            text-align: right;
        }

        th.label,
        td.label {
            text-align: left;
            background: #f1f1f1;
            font-weight: bold;
        }

        tr.group-header td {
            background: #e9ecef;
            font-weight: bold;
            text-align: left;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tr.total-row td {
            font-weight: bold;
            background: #d4edda;
            color: #155724;
            font-size: 15px;
        }

        .muted {
            color: #666;
            font-style: italic;
        }

        .thank-you {
            text-align: center;
            margin-top: 30px;
            font-size: 14px;
            color: #444;
        }

        .footer {
            text-align: center;
            font-size: 13px;
            margin-top: 25px;
            color: #888;
            font-style: italic;
        }
    </style>
</head>

<body>
    <div class="brand">🏨 KHÁCH SẠN HOBILO</div>
    <div class="sub-brand">Hóa đơn điện tử – Thanh toán dịch vụ lưu trú</div>

    <h2>HÓA ĐƠN THANH TOÁN</h2>

    <div class="invoice-info">
        <p><strong>Mã hóa đơn:</strong> {{ $invoiceCode }}</p>
        <p><strong>Ngày tạo:</strong> {{ $issuedDate }}</p>
        <p>
            <strong>Check-in:</strong>
            {{ $fmtCheckin ?? optional($invoice->booking->check_in_date)->format('d/m/Y H:i') }}
        </p>
        <p>
            <strong>Check-out:</strong>
            {{ $fmtCheckout ?? optional($invoice->booking->check_out_date)->format('d/m/Y H:i') }}
        </p>
        <p><strong>Hình thức tính:</strong>
            @if($isHourly)
            Theo giờ ({{ $durationValue }} giờ)
            @else
            Theo ngày ({{ $durationValue }} đêm)
            @endif
        </p>
        <p><strong>Khách hàng:</strong> {{ $customerName }}</p>
        <p><strong>Email:</strong> {{ $customerEmail }}</p>
        <p><strong>SĐT:</strong> {{ $customerPhone }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th class="label">Mục</th>
                <th>Chi tiết</th>
                <th>Số lượng</th>
                <th>Đơn giá</th>
                <th>Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            {{-- PHÒNG --}}
            @if(!empty($roomLines))
            <tr class="group-header">
                <td colspan="5">Chi tiết phòng</td>
            </tr>
            @foreach ($roomLines as $line)
            @php
            $unitText = ($line['unit'] ?? 'night') === 'hour' ? 'giờ' : 'đêm';
            $labelLeft = 'Phòng ' . ($line['room_number'] ?? '');
            $roomTypeName = $line['room_type'] ?? ($invoice->booking->rooms->firstWhere('room_number', $line['room_number'])->roomType->name ?? '---');
            @endphp
            <tr>
                <td class="label">{{ $labelLeft }}</td>
                <td>{{ $roomTypeName }}</td>
                <td>{{ (int)($line['unit_count'] ?? 0) }} {{ $unitText }}</td>
                <td>{{ vnd($line['base_rate'] ?? 0) }}</td>
                <td>{{ vnd($line['total'] ?? 0) }}</td>
            </tr>
            @endforeach
            @endif

            {{-- DỊCH VỤ --}}
            @if(!empty($serviceLines))
            <tr class="group-header">
                <td colspan="5">Dịch vụ đã sử dụng</td>
            </tr>
            @foreach ($serviceLines as $s)
            <tr>
                <td class="label">Dịch vụ</td>
                <td>{{ $s['name'] ?? '' }}</td>
                <td>{{ (int)($s['quantity'] ?? 0) }}</td>
                <td>{{ vnd($s['price'] ?? 0) }}</td>
                <td>{{ vnd($s['total'] ?? 0) }}</td>
            </tr>
            @endforeach
            @endif

            {{-- TIỆN NGHI PHÁT SINH --}}
            @if(!empty($amenityLines))
            <tr class="group-header">
                <td colspan="5">Tiện nghi phát sinh</td>
            </tr>
            @foreach ($amenityLines as $a)
            <tr>
                <td class="label">Tiện nghi</td>
                <td>
                    {{ $a['amenity_name'] ?? '' }}
                    <span class="muted">(@if(!empty($a['room_number'])) phòng {{ $a['room_number'] }} @endif)</span>
                </td>
                <td>{{ (int)($a['quantity'] ?? 0) }}</td>
                <td>{{ vnd($a['price'] ?? 0) }}</td>
                <td>{{ vnd($a['total'] ?? 0) }}</td>
            </tr>
            @endforeach
            @endif

            {{-- CÁC KHOẢN ĐIỀU CHỈNH --}}
            <tr class="group-header">
                <td colspan="5">Các khoản điều chỉnh</td>
            </tr>
            <tr>
                <td class="label">Giảm giá</td>
                <td colspan="3"></td>
                <td>-{{ vnd($discountSaved) }}</td>
            </tr>
            <tr>
                <td class="label">Đặt cọc</td>
                <td colspan="3"></td>
                <td>-{{ vnd($depositSaved) }}</td>
            </tr>

            {{-- TỔNG CỘNG (ưu tiên số đã chốt trong hóa đơn) --}}
            <tr class="total-row">
                <td class="label">TỔNG CỘNG</td>
                <td colspan="3"></td>
                <td>{{ vnd($finalAmountSaved) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="thank-you">
        Cảm ơn quý khách đã sử dụng dịch vụ của chúng tôi! <br>
        Hân hạnh được phục vụ quý khách lần sau.
    </div>

    <div class="footer">
        Hotline: 0862 332 128 – Email: support@hobilo.vn<br>
        Địa chỉ: Trịnh Văn Bô, Nam Từ Liêm, Hà Nội
    </div>
</body>

</html>
