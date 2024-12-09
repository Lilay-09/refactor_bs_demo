
<!DOCTYPE html>
<html>
<head>
    <title>{{ $title ?? 'History' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
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
    </style>
</head>
<body>
    <h3 class="header">{{ $title ?? 'History Packages'}}</h3>
    <p>Date: {{ $date ?? 'date' }}</p>
    <p>Driver: {{ $driver['user_name'] }}({{ $driver['phone'] }})</p>
    <div class="content">
        @if(isset($data[0]))
            @foreach($data as $key => $tracking)
                <h4>Tracking Number: {{ $tracking['fleet_number'] ?? 'N/A' }}</h4>
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
                        @foreach($tracking['details'] ?? [] as $idx => $item)
                            <tr>
                                <td>{{ $idx + 1 }}</td>
                                <td>
                                    <span>{{ $item->merchant_phone }}</span>
                                    <small>({{ $item->merchant_name }})</small>
                                </td>
                                <td>{{ $item->receiver_phone }}</td>
                                <td>{{ $item->receiver_address }}</td>
                                <td>{{ $item->status_code }}</td>
                                <td>${{ $item->total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div style="display: flex; flex-direction: column; min-height: 100vh; justify-content: flex-end; width: 100%;">
                    <p style="text-align: right; margin-top: auto;">Grand Total ${{ $tracking['total']['grand'] }}</p>
                </div>

            @endforeach
        @else
            <p>No data available</p>
        @endif
    </div>
</body>
</html>
