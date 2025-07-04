@php
use Carbon\Carbon;
@endphp

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

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
        <p><strong>Mã hóa đơn:</strong> {{ $invoice->invoice_code }}</p>
        <p><strong>Ngày tạo:</strong> {{ Carbon::parse($invoice->issued_date)->format('d/m/Y') }}</p>
        <p><strong>Check-in:</strong> {{ Carbon::parse($invoice->booking->check_in_date)->format('d/m/Y') }}</p>
        <p><strong>Check-out:</strong> {{ Carbon::parse($invoice->booking->check_out_date)->format('d/m/Y') }}</p>
        <p><strong>Khách hàng:</strong> {{ $invoice->booking->customer->name ?? '---' }}</p>
        <p><strong>Email:</strong> {{ $invoice->booking->customer->email ?? '---' }}</p>
        <p><strong>SĐT:</strong> {{ $invoice->booking->customer->phone ?? '---' }}</p>
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
            <!-- Chi tiết phòng -->
            @if(count($invoice->booking->rooms ?? []))
            <tr class="group-header">
                <td colspan="5">Chi tiết phòng</td>
            </tr>
            @foreach ($invoice->booking->rooms as $room)
            <tr>
                <td class="label">Phòng {{ $room->room_number }}</td>
                <td>{{ $room->roomType->name ?? '---' }}</td>
                <td>{{ $room->nights }} đêm</td>
                <td>{{ number_format($room->roomType->base_rate ?? 0, 0, ',', '.') }} đ</td>
                <td>{{ number_format($room->room_total ?? 0, 0, ',', '.') }} đ</td>
            </tr>
            @endforeach
            @endif

            <!-- Dịch vụ -->
            @if(count($invoice->booking->services ?? "Không có dịch vụ nào được sử dụng"))
            <tr class="group-header">
                <td colspan="5">Dịch vụ đã sử dụng</td>
            </tr>
            @foreach ($invoice->booking->services as $service)
            @php
            $qty = $service->pivot->quantity ?? 0;
            $price = $service->price ?? 0;
            $total = $qty * $price;
            @endphp
            <tr>
                <td class="label">Dịch vụ</td>
                <td>{{ $service->name }}</td>
                <td>{{ $qty }}</td>
                <td>{{ number_format($price, 0, ',', '.') }} đ</td>
                <td>{{ number_format($total, 0, ',', '.') }} đ</td>
            </tr>
            @endforeach
            @endif

            <!-- Điều chỉnh -->
            <tr class="group-header">
                <td colspan="5">Các khoản điều chỉnh</td>
            </tr>
            <tr>
                <td class="label">Giảm giá</td>
                <td colspan="3"></td>
                <td>-{{ number_format($invoice->discount_amount, 0, ',', '.') }} đ</td>
            </tr>
            <tr>
                <td class="label">Đặt cọc</td>
                <td colspan="3"></td>
                <td>-{{ number_format($invoice->deposit_amount, 0, ',', '.') }} đ</td>
            </tr>

            <!-- Tổng cộng -->
            <tr class="total-row">
                <td class="label">TỔNG CỘNG</td>
                <td colspan="3"></td>
                <td>{{ number_format($invoice->total_amount, 0, ',', '.') }} đ</td>
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
