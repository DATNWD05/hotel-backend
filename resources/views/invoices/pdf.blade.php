<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 14px;
            color: #333;
            padding: 40px;
            background-color: #fff;
        }

        .brand {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #007BFF;
            margin-bottom: 4px;
        }

        .sub-brand {
            text-align: center;
            font-size: 14px;
            color: #666;
            margin-bottom: 12px;
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 20px;
            text-transform: uppercase;
        }

        .invoice-info {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #ccc;
            background-color: #f8f9fa;
            border-radius: 6px;
        }

        .invoice-info p {
            margin: 6px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: #fff;
        }

        th,
        td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: right;
        }

        th.label,
        td.label {
            text-align: left;
            background-color: #f0f0f0;
            font-weight: 600;
        }

        tr:last-child td {
            font-weight: bold;
            background-color: #e9f7ef;
            color: #155724;
        }

        .thank-you {
            text-align: center;
            margin-top: 40px;
            font-size: 15px;
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
        <p><strong>Ngày tạo:</strong> {{ \Carbon\Carbon::parse($invoice->issued_date)->format('d/m/Y') }}</p>
        <p><strong>Khách hàng:</strong> {{ $invoice->booking->customer->name ?? '---' }}</p>
        <p><strong>Email:</strong> {{ $invoice->booking->customer->email ?? '---' }}</p>
    </div>

    <table>
        <tr>
            <th class="label">Tien phong</th>
            <td>{{ number_format($invoice->room_amount, 0, ',', '.') }} đ</td>
        </tr>
        <tr>
            <th class="label">Dich vu</th>
            <td>{{ number_format($invoice->service_amount, 0, ',', '.') }} đ</td>
        </tr>
        <tr>
            <th class="label">Giam gia</th>
            <td>-{{ number_format($invoice->discount_amount, 0, ',', '.') }} đ</td>
        </tr>
        <tr>
            <th class="label">Dat coc</th>
            <td>-{{ number_format($invoice->deposit_amount, 0, ',', '.') }} đ</td>
        </tr>
        <tr>
            <th class="label">TONG CONG</th>
            <td>{{ number_format($invoice->total_amount, 0, ',', '.') }} đ</td>
        </tr>
    </table>

    <div class="thank-you">
        Xin chân thành cảm ơn quý khách đã tin tưởng và sử dụng dịch vụ của chúng tôi! <br>
        Hân hạnh được phục vụ quý khách trong những lần tiếp theo.
    </div>

    <div class="footer">
        Hotline hỗ trợ: 0862 332 128 – Email: support@hobilo.vn<br>
        Địa chỉ: Trịnh Văn Bô, Q. Nam Từ Liêm, TP.Hà Nội
    </div>

</body>

</html>
