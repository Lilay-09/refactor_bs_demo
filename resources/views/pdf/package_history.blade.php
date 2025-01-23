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
        font-family: "khmeros";
    }


    /*
        h1, p, td {
            font-family: 'Noto Sans Khmer', sans-serif;
        } */
        .header {
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .content {
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #dee2e6;
            text-align: center;
            vertical-align: middle;
            padding: 8px;
        }
        th {
            background-color: #f8f9fa;
        }
        .no-data {
            text-align: center;
            font-style: italic;
            color: #6c757d;
        }
        .footer {
            text-align: right;
            margin-top: auto;
            font-weight: bold;
        }
        @media (max-width: 768px) {
            table {
                font-size: 12px;
            }
            .header {
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <h3 class="header">{{ $title ?? 'History Packages' }}</h3>
    <p>Date: {{ $date ?? now()->format('Y-m-d') }}</p>
    <p>Driver: {{ $driver['user_name'] ?? 'N/A' }} ({{ $driver['phone'] ?? '' }})</p>
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
                                        <small>({{ $item->merchant_name ?? '' }})</small>
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




