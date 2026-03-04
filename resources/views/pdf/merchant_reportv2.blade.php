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
        margin: 20px;
        font-size: 14px;
    }

    .header {
        text-align: center;
        font-size: 20px;
        font-weight: bold;
        margin-bottom: 15px;
    }
    
    .info-row {
        margin-bottom: 8px;
    }
    
    .content {
        margin-top: 20px;
    }
    
    .date-header {
        font-weight: bold;
        margin-top: 15px;
        margin-bottom: 10px;
        font-size: 16px;
    }
    
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 5px;
    }
    
    th, td {
        border: 1px solid #000;
        text-align: center;
        vertical-align: middle;
        padding: 8px 4px;
        font-size: 13px;
    }
    
    th {
        background-color: #d9e9f7;
        font-weight: bold;
    }
    
    .text-left {
        text-align: left;
        padding-left: 8px;
    }
    
    .text-right {
        text-align: right;
        padding-right: 8px;
    }
    
    .no-border {
        border: none;
        background-color: #fff;
    }
    
    .no-border-left {
        border-left: none;
    }
    
    .no-border-right {
        border-right: none;
    }
    
    .status-delivered {
        color: #00b050;
        font-weight: bold;
    }
    
    .status-returned {
        color: #ff0000;
        font-weight: bold;
    }
    
    .status-pending {
        color: #7030a0;
        font-weight: bold;
    }
    
    .status-cancelled {
        color: #808080;
        font-weight: bold;
    }
    
    .yellow-bg {
        background-color: #ffff00;
    }
    
    .summary-row {
        background-color: #fff;
    }
    
    .summary-label {
        text-align: left;
        padding-left: 8px;
        font-weight: normal;
    }
    
    .summary-value-red {
        color: #ff0000;
        font-weight: normal;
    }
    
    .summary-value-blue {
        color: #0070c0;
        font-weight: normal;
    }
    
    .no-data {
        text-align: center;
        font-style: italic;
        color: #6c757d;
    }
    </style>
</head>
<body>
    <h3 class="header">{{ $title ?? 'History Packages' }}</h3>
    <div class="info-row"><strong>Date:</strong> {{ $date ?? now()->format('Y-m-d') }}</div>
    <div class="info-row"><strong>Merchant:</strong> {{ $merchant['username'] ?? 'N/A' }} ({{ $merchant['phone'] ?? '' }})</div>

    <div class="content">
        @if(isset($data[0]))
            @foreach($data as $key => $tracking)
                <div class="date-header">{{ $tracking['date'] }}</div>
                <table>
                    <thead>
                        <tr>
                            <th rowspan="2" style="width: 35px;">ល.រ</th>
                            <th rowspan="2" style="width: 120px;">ទីតាំង</th>
                            <th rowspan="2" style="width: 90px;">លេខទូរស័ព្ទ</th>
                            <th colspan="2" style="width: 120px;">COD</th>
                            <th rowspan="2" style="width: 60px;">សេវា</th>
                            <th colspan="2" style="width: 120px;">Total</th>
                            <th rowspan="2" style="width: 90px;">ស្ថានភាព</th>
                            <th rowspan="2" style="width: 100px;">មូលហេតុ</th>
                        </tr>
                        <tr>
                            <th style="width: 60px;">ដុល្លា</th>
                            <th style="width: 60px;">រៀល</th>
                            <th style="width: 60px;">ដុល្លា</th>
                            <th style="width: 60px;">រៀល</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(isset($tracking['list']))
                            @php
                                $statusColors = [
                                    10 => 'status-returned',
                                    9  => 'status-delivered',
                                    19 => 'status-pending',
                                    11 => 'status-cancelled'
                                ];
                            @endphp
                            @foreach($tracking['list'] as $idx => $item)
                                <tr>
                                    <td>{{ $idx + 1 }}</td>
                                    <td class="text-left">{{ $item['receiver_address'] ?? '' }}</td>
                                    <td>{{ $item['receiver_phone'] ?? '' }}</td>
                                    <td class="text-right">${{ $item['cod_usd'] }}</td>
                                    <td class="text-right">${{ $item['cod_khr'] }}</td>
                                    <td class="text-right">${{ $item['fees'] }}</td>
                                    <td class="text-right">${{ $item['total_usd']}}</td>
                                    <td class="text-right">{{ $item['total_khr'] }}</td>
                                    <td class="{{ $statusColors[$item['status_id']] ?? '' }}">{{ $item['status_code'] ?? '' }}</td>
                                    <td class="text-left">{{ $item['delivery_remarks'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="10" class="no-data">No details available</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
                
                <!-- Summary rows -->
                <table>
                    <tr class="summary-row">
                        <td colspan="3" class="no-border"></td>
                        <td class="summary-label">សរុបសេវាជំពាក់:</td>
                        <td class="text-right summary-value-red">${{ $tracking['total']['owe_usd'] ?? 0 }}</td>
                        <td class="text-right summary-value-blue">0</td>
                        <td colspan="2" class="no-border"></td>
                    </tr>
                    <tr class="summary-row">
                        <td colspan="3" class="no-border"></td>
                        <td class="summary-label">សរុបទឹកប្រាក់:</td>
                        <td class="text-right summary-value-blue">${{ $tracking['total']['cod_usd'] ?? 0 }}</td>
                        <td class="text-right summary-value-blue">${{ $tracking['total']['cod_khr'] ?? 0 }}</td>
                        <td class="no-border"></td>
                    </tr>
                    <tr class="summary-row">
                        <td colspan="3" class="no-border"></td>
                        <td class="summary-label">សរុបសេវា:</td>
                        <td class="text-right summary-value-red">${{ number_format($tracking['total']['fees'] ?? 0, 2) }}</td>
                        <td class="text-right summary-value-blue">0</td>
                        <td class="no-border"></td>
                    </tr>
                    <tr class="summary-row">
                        <td colspan="3" class="no-border"></td>
                        <td class="summary-label">សរុបទូទាត់:</td>
                        <td class="text-right summary-value-blue">${{ $tracking['total']['usd'] ?? 0 }}</td>
                        <td class="text-right summary-value-blue">${{ $tracking['total']['khr'] ?? 0 }}</td>
                        <td class="no-border"></td>
                    </tr>
                </table>
            @endforeach
        @else
            <p class="no-data">No data available</p>
        @endif
    </div>
</body>
</html>