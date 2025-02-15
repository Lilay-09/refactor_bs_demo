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
    <p>Merchant: {{ $merchant['user_name'] ?? 'N/A' }} ({{ $merchant['phone'] ?? '' }})</p>
    <div class="content">

        @if(isset($data[0]))
            @foreach($data as $key => $tracking)
                {{$tracking['date']}}
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Receiver Address</th>
                            <th>Receiver Phone</th>
                            <th>COD</th>
                            <th>Fees</th>
                            <th>Status</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(isset($tracking['list']))
                            @php
                                $colorCode = [
                                    10 => '#e64349',
                                    9  => '#2fbb66',
                                    19 => '#cf4bdf',
                                    11 => '#808080'
                                ];
                            @endphp
                            @foreach($tracking['list'] as $idx => $item)
                                <tr>
                                    <td>{{ $idx + 1 }}</td>
                                    <td>{{ $item['receiver_address'] ?? '' }}</td>
                                    {{-- <td>
                                        <span>{{ $item['driver_name'] ?? '' }}</span>
                                        <small>({{ $item['driver_phone'] ?? '' }})</small>
                                    </td> --}}
                                    <td>{{ $item['receiver_phone'] ?? '' }}</td>
                                    <td>{{ $item['price'] ?? '' }}</td>
                                    <td>{{ $item['delivery_fee'] ?? '' }}</td>
                                    <td style="color:{{$colorCode[$item['status_id']] ?? ''}}">{{ $item['status_code'] ?? '' }}</td>
                                    <td>${{ number_format($item['total'] ?? 0, 2) }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="6" class="no-data">No details available</td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="3" style="border: none;"></td>
                            <td>{{$tracking['total']['price'] ?? ''}}</td>
                            <td>{{$tracking['total']['fees'] ?? ''}}</td>
                        </tr>
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




