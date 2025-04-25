<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $title ?? 'History Packages' }}</title>
    <style>
    @font-face {
        font-family: khmeros;
        src: url("{{ public_path('fonts/khmeros.ttf') }}");
    }

    body {
        font-family: "khmeros", sans-serif;
        background-color: #f4f6f8;
        color: #333;
        margin: 20px;
    }

    .header {
        text-align: center;
        font-size: 28px;
        font-weight: bold;
        margin-bottom: 30px;
        color: #343a40;
    }

    p {
        margin: 4px 0 8px 0;
        font-size: 16px;
    }

    .content {
        margin-top: 30px;
    }

    h4 {
        background-color: #007bff;
        color: white;
        padding: 10px;
        border-radius: 8px 8px 0 0;
        margin-top: 40px;
        margin-bottom: 0;
    }

    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 30px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
        background-color: #fff;
        border-radius: 0 0 8px 8px;
        overflow: hidden;
    }

    th {
        background-color: #e9ecef;
        color: #495057;
        font-weight: 600;
        padding: 12px;
        border-bottom: 1px solid #dee2e6;
    }

    td {
        padding: 12px;
        border-bottom: 1px solid #dee2e6;
        vertical-align: middle;
        text-align: center;
    }

    .no-data {
        text-align: center;
        font-style: italic;
        color: #6c757d;
        padding: 12px;
        background-color: #fff3cd;
    }

    .footer {
        text-align: right;
        font-weight: bold;
        margin-top: -10px;
        margin-bottom: 50px;
        color: #212529;
    }

    @media (max-width: 768px) {
        table, th, td {
            font-size: 12px;
        }

        .header {
            font-size: 20px;
        }

        p {
            font-size: 14px;
        }
    }
    </style>
</head>
<body>
    <h3 class="header">{{ $title ?? 'History Packages' }}</h3>
    <p>Date: {{ $date ?? now()->format('Y-m-d') }}</p>
    <p>Driver: {{ $driver['user_name'] ?? 'N/A' }} ({{ $driver['phone'] ?? '' }})</p>

    <h3>Summary</h3>
    <p style="margin-left: 8px">Delivered: {{ $deliveredCount }} pcs</p>
    <p style="margin-left: 8px">Failed With Fee: {{ $failedWithFeeCount }} pcs</p>
    <p style="margin-left: 8px">Grand Total: ${{ $grandTotal }}</p>

    <div class="content">
        @if(isset($data[0]))
            @foreach($data as $key => $tracking)
                <h4>Tracking Number: {{ $tracking['fleet_number'] ?? '' }}</h4>
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Merchant</th>
                            <th>Receiver Phone</th>
                            <th>Receiver Address</th>
                            <th>Status</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(isset($tracking['details'][0]))
                            @foreach($tracking['details'] as $idx => $item)
                                <tr>
                                    <td>{{ $idx + 1 }}</td>
                                    <td>
                                        <span>{{ $item->merchant_phone ?? '' }}</span>
                                        <br>
                                        <small style="color: #6c757d;">({{ $item->merchant_name ?? '' }})</small>
                                    </td>
                                    <td>{{ $item->receiver_phone ?? '' }}</td>
                                    <td>{{ $item->receiver_address ?? '' }}</td>
                                    <td>{{ $item->status_code ?? '' }}</td>
                                    <td>${{ number_format($item->total ?? 0, 2) }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="6" class="no-data">No details available</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
                <div class="footer">
                    Grand Total: ${{ number_format($tracking['total']['grand'] ?? 0, 2) }}
                </div>
            @endforeach
        @else
            <p class="no-data">No data available</p>
        @endif
    </div>
</body>
</html>
