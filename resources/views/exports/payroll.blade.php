<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Bảng lương tháng {{ $month }}/{{ $year }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
        }

        h2 {
            text-align: center;
            margin-top: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: center;
        }

        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <h2>BẢNG LƯƠNG NHÂN VIÊN THÁNG {{ str_pad($month, 2, '0', STR_PAD_LEFT) }}/{{ $year }}</h2>

    <table>
        <thead>
            <tr>
                <th>Mã NV</th>
                <th>Tên nhân viên</th>
                <th>Tổng giờ</th>
                <th>Số ngày công</th>
                <th>Tổng lương</th>
                <th>Thưởng</th>
                <th>Phạt</th>
                <th>Lương cuối</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payrolls as $p)
                <tr>
                    <td>{{ $p->employee->MaNV ?? '-' }}</td>
                    <td>{{ $p->employee->name ?? '-' }}</td>
                    <td>{{ $p->total_hours ?? 0 }}</td>
                    <td>{{ $p->total_days ?? 0 }}</td>
                    <td>{{ number_format($p->total_salary ?? 0, 0, ',', '.') }} đ</td>
                    <td>{{ number_format($p->bonus ?? 0, 0, ',', '.') }} đ</td>
                    <td>{{ number_format($p->penalty ?? 0, 0, ',', '.') }} đ</td>
                    <td>{{ number_format($p->final_salary ?? 0, 0, ',', '.') }} đ</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>

</html>
