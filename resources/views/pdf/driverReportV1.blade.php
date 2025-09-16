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
    *{
        margin: 0;
        box-sizing: border-box;
        padding: 0;
    }
    body {
        margin: 0;
        font-family: "khmeros";
    }
    .devider{
        height: 5px;
        width: 100%;
        background-color: rgba(255, 255, 0, 1);
    }
    .table-container {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    width: 100%;
    }

    .table {
    border-collapse: collapse;
    width: 100%;
    min-width: 800px; /* force horizontal scroll on smaller screens */
    }

    .table th,
    .table td {
    white-space: nowrap;
    padding: 8px;
    border: 1px solid #ccc;
    text-align: left;
    font-size: 14px;
    }
    .table th{
        background-color: rgba(255, 255, 0, 1);
        font-weight: 300;
        color: rgba(255, 3, 2, 1);
    }



    </style>
</head>
<body>
    <table width="100%" style="border-collapse: collapse; margin-bottom: 10px;">
        <!-- Row 1: Title -->
        <tr>
            <td colspan="3" style="text-align: center; font-size: 21px;">
                របាយការណ៏អ្នកដឹក
            </td>
        </tr>

        <!-- Row 2: Logo (left), Empty (middle), Date (right) -->
        <tr>
            <td style="width: 30%; text-align: left; vertical-align: bottom;">
                @if (!empty($logo))
                    <img src="{{ $logo }}" alt="Logo" style="height: 50px;">
                @endif
            </td>

            <td style="width: 20%;"></td>

            <td style="width: 50%; text-align: right; vertical-align: bottom;">
                កាលបរិច្ឆេទ៖ {{ $date ?? now()->format('Y-m-d') }}
            </td>
        </tr>
    </table>
    <div class="devider"></div>
    <div>
        <div style="overflow: hidden; margin-bottom: 5px;">
            <div style="float: left; width: 50%;">
                <span>កញ្ចប់ទទួល៖</span>
                <span>{{$pickedUpCount ?? 0}} កញ្ចប់</span>
            </div>
            <div style="float: right; width: 50%; text-align: right;">
                {{$driver->username ?? ''}}
            </div>
        </div>

        <div style="overflow: hidden; margin-bottom: 5px;">
            <div style="float: left; width: 50%;">
                <span>ដឹកជោគជ័យ៖</span>
                <span>{{$deliveredCount ?? 0}} កញ្ចប់</span>
            </div>
            <div style="float: right; width: 50%; text-align: right;">
                អ្នកដឹកជញ្ជូន
            </div>
        </div>

        <div style="overflow: hidden;">
            <div style="float: left; width: 50%;">
                <span>ដឹកបរាជ័យ៖</span>
                <span>{{$failedCount ?? 0}} កញ្ចប់</span>
            </div>
            <div style="float: right; width: 50%; text-align: right;">
                {{$driver->phone ?? ''}}
            </div>
        </div>
    </div>

    <div class="content">
        @if(isset($data[0]))
            @foreach($data as $key => $tracking)
                <h4 style="text-align: right;">កាលបរិច្ឆេទ៖: {{ $tracking['date'] ?? '' }}</h4>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                            <th>ល.រ</th>
                            <th>អតិថិជន</th>
                            <th>ទីតាំងដឹក</th>
                            <th>លេខទទួល</th>
                            <th>ប្រមូលបាន</th>
                            <th
                                style="
                                    text-align: center;
                                    vertical-align: middle;
                                    letter-spacing: 0.1px;
                                    white-space: nowrap;
                                    width: 60px;
                                    font-family: 'Noto Sans Khmer', 'Khmer OS', sans-serif;
                                    font-size: 10pt;
                                    line-height: 1.6;
                                ">
                                <div style="display: flex; align-items: center; justify-content: center; height: 100%;">
                                    តាក់ស៊ី
                                </div>
                            </th>
                            <th>សរុប</th>
                            {{-- <th colspan="2" style="text-align:center;">ប្រាក់បានទទួល</th> <!-- spans 2 cols --> --}}
                            <th>ស្ថានភាព</th>
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
                                    <td>{{ $item->receiver_address ?? '' }}</td>
                                    <td>{{ $item->receiver_phone ?? '' }}</td>
                                    <td>{{ $item->driver_collected}}</td>
                                    <td>{{ $item->taxi_fee ?? '' }}</td>
                                    <td>${{ number_format($item->total ?? 0, 2) }}</td>
                                    {{-- <td>$3</td>        <!-- 1st column under colspan -->
                                    <td>4000 KHR</td>  <!-- 2nd column under colspan --> --}}
                                    <td style="color: {{ $item->status_id == 9 ? 'green' : ($item->status_id == 10 ? 'red' : 'grey') }}">
                                        {{ $item->status_code ?? '' }}
                                    </td>
                                    </tr>
                                @endforeach
                                @else
                            @endif
                        </tbody>
                        <tfoot>
                            <tr>
                                {{-- {{$tracking}} --}}
                                <td colspan="4" style="border: none"></td>
                                <td style="border: none;text-decoration: underline">{{ $tracking['total']['collected'] ?? 0 }}</td>
                                <td style="border: none;text-decoration: underline">{{ $tracking['total']['taxi_fee'] ?? 0 }}</td>
                                <td style="border: none;text-decoration: underline">{{ $tracking['total']['grand'] ?? 0 }}</td>
                                {{-- <td></td> <!-- empty cell to match number of columns --> --}}
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div style="background-color: rgba(255, 255, 0, 1);height:10px;width:100%;"></div>
                <div style="text-align: right; margin: 5px 0;">
                    សរុបប្រាក់ត្រូវទូរទាត់៖ {{ $tracking['total']['toBeSettled'] ?? '' }}
                </div>
                <div style="background-color: rgba(255, 206, 4, 1);height:10px;width:100%"></div>
            @endforeach
        @else
            <p class="no-data">No data available</p>
        @endif
    </div>






    {{-- <h3 class="header">{{ $title ?? 'History Packages' }}</h3>
    <p>Date: {{ $date ?? now()->format('Y-m-d') }}</p>
    <p>Driver: {{ $driver['username'] ?? 'N/A' }} ({{ $driver['phone'] ?? '' }})</p>
    <h3>Summary</h3>
    <p style="margin-left: 8px;padding:0px">Delivered: {{$deliveredCount}} pcs</p>
    <p style="margin-left: 8px;padding:0px">Failed With Fee: {{$failedWithFeeCount}} pcs</p>
    <p style="margin-left: 8px">Grand Total: ${{$grandTotal}}</p>
    <div class="content"> --}}
        {{-- @if(isset($data[0]))
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
        @endif --}}
    </div>
</body>
</html>




