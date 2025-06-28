<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 14px;
            color: #333;
            padding: 30px;
            background-color: #fdfdfd;
        }

        h2 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        p {
            margin: 4px 0;
        }

        .invoice-info {
            margin-bottom: 20px;
            border: 1px solid #ccc;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }

        th,
        td {
            padding: 10px 12px;
            border: 1px solid #dee2e6;
            text-align: right;
        }

        th.label,
        td.label {
            text-align: left;
            font-weight: 600;
            background-color: #f0f0f0;
        }

        tr:last-child td {
            font-weight: bold;
            background-color: #e9f7ef;
            color: #155724;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-style: italic;
            color: #777;
        }

        .brand {
            text-align: center;
            font-size: 18px;
            margin-bottom: 6px;
            font-weight: bold;
            color: #007BFF;
        }

        .thank-you {
            text-align: center;
            font-size: 16px;
            margin-top: 24px;
            color: #555;
        }
    </style>
</head>

<body>
    <div class="brand">🏨 KHÁCH SẠN Hobilo</div>
    <h2>HÓA ĐƠN THANH TOÁN</h2>

    <div class="invoice-info">
        <p><strong>Mã hóa đơn:</strong> {{ $invoice->invoice_code }}</p>
        <p><strong>Ngày lập:</strong> {{ \Carbon\Carbon::parse($invoice->issued_date)->format('d/m/Y') }}</p>
        <p><strong>Mã booking:</strong> {{ $invoice->booking_id }}</p>
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
        Xin chân thành cảm ơn quý khách đã sử dụng dịch vụ của chúng tôi! <br>
        Hân hạnh được phục vụ quý khách trong những lần tiếp theo.
    </div>

    <div class="footer">
        Hotline hỗ trợ: 1900 1234 – Email: support@khachsanabc.vn
    </div>
</body>

</html>
