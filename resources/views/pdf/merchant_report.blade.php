
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report</title>
</head>
<style>
    *{
        margin: 0;
        box-sizing: border-box;
    }
    @font-face {
        font-family: khmeros;
        src: url("{{ public_path('fonts/khmeros.ttf') }}");
    }

    body{
        font-family: "khmeros";
        display: flex;
        flex-direction: column;
        width: 100%;
        align-items: center;
        position: relative;
        justify-content: center;
    }
    body > div{
        width: 90%;
    }
    .header{
        display:  flex;
        position: relative;
        align-items: center;
        justify-content: space-between;
    }

    .divided{
        height: 5px;
        background-color: #223BC9;
    }
    .summary {
        display: flex;
        justify-content: space-between;
        align-items: center;
        width: 100%;
    }

    p{
        padding-bottom: 8px;
        margin: 0;
    }

    .table-wrapper {
        overflow-x: auto;
      }

      table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        min-width: 600px;
      }

      th, td {
        border: 1px solid #000;
        padding: 5px;
        text-align: center;
      }

      .green {
        color: green;
      }

      .orange {
        color: orange;
      }

      .blue {
        background: #223BC9;
        color: white;
        font-weight: bold;
      }

      .totals {
        margin-top: 10px;
        font-size: 16px;
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
      }

      .total-amount {
        font-weight: bold;
        font-size: 18px;
      }

      .footer-bar {
        background: #223BC9;
        height: 20px;
        margin-top: 20px;
      }


      @media (max-width: 600px) {
        body > * {
          width: 100%;
        }

        table {
          font-size: 10px;
          min-width: unset;
        }

        .table-wrapper {
          overflow-x: auto;
        }
      }
      .no-border > td{
        border: none;
      }

</style>
<body>
    @php
        $hasLogo = !empty($logo);
    @endphp

    <div class="header" style="text-align: center;">
        @if ($hasLogo)
            <!-- Left Section (Logo) -->
            <div class="logo" style="float: left; width: 30%; text-align: left;">
                <img src="{{ $logo }}" alt="Logo" style="width: 100px;">
            </div>

            <!-- Center Section (Title + Date) -->
            <div class="title" style="float: left; width: 40%; text-align: center;">
                <div>របាយការណ៏អតិថិជន</div>
                <div>កាលបរិច្ឆេទ៖</div>
                <div>{{ $date ?? now()->format('Y-m-d') }}</div>
            </div>

            <!-- Clear floats -->
            <div style="clear: both;"></div>
        @else
            <!-- Centered Title Only (when no logo) -->
            <div class="title" style="width: 100%; text-align: center;">
                <div>របាយការណ៏អតិថិជន</div>
                <div>កាលបរិច្ឆេទ៖</div>
                <div>{{ $date ?? now()->format('Y-m-d') }}</div>
            </div>
        @endif
    </div>


    <div class="divided"></div>
    <div class="summary" style="text-align: center;">
        <!-- Left Section (Package Info) -->
        <div class="packageInfo" style="float: left; width: 45%; text-align: left;">
            <p>
                <span>កញ្ចប់ក្រេឌីត៖</span>
                <span>0</span>
            </p>
            <p>
                <span>ប្រើកញ្ចប់អស់៖</span>
                <span>0</span>
            </p>
            <p>
                <span>កញ្ចប់ក្រេឌីតសល់៖</span>
                <span>0</span>
            </p>
        </div>

        <!-- Right Section (Profile) -->
        <div class="profile" style="float: right; width: 45%; text-align: right;">
            <p>ដៃគូសហការធម្មតា</p>
            <p>{{$merchant['user_name']}}</p>
            <p>{{$merchant['phone']}}</p>
        </div>

        <!-- Clear the float -->
        <div style="clear: both;"></div>
    </div>

    @if(isset($data[0]))
            @foreach($data as $key => $tracking)
    <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>ល.រ</th>
              <th>ទីតាំងដឹក</th>
              <th>លេខទទួល</th>
              <th>COD</th>
              <th>សេវា</th>
              <th>សរុប</th>
              <th>ស្ថានភាព</th>
              <th>ផ្សេងៗ</th>
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
                        <td>{{ $item['receiver_phone'] ?? '' }}</td>
                        <td>{{ $item['price'] ?? '' }}</td>
                        <td>{{ $item['delivery_fee'] ?? '' }}</td>
                        <td style="color:{{$colorCode[$item['status_id']] ?? ''}}">{{ $item['status_code'] ?? '' }}</td>
                        <td>${{ number_format($item['total'] ?? 0, 2) }}</td>
                        <td>{{ $item['remarks'] ?? '' }}</td>
                    </tr>
                @endforeach
            @else
                <tr>
                    <td colspan="8" class="no-data">No details available</td>
                </tr>
            @endif
            <tr>
                <td colspan="2" style="border: none;"></td>
                <td style="border: none;">សរុប</td>
                <td style="border: none;">{{ $tracking['total']['price'] ?? '' }}</td>
                <td style="border: none;">{{ $tracking['total']['fees'] ?? '' }}</td>
                <td colspan="3" style="border: none; text-align: left;">
                    ${{ number_format($tracking['total']['grand'] ?? 0, 2) }}
                </td>
            </tr>

          </tbody>
        </table>
      </div>

      <div class="divided"></div>

      <div class="totals" style="width: 100%;">
            <div style="float: right; text-align: right;">
                <strong>សរុបប្រាក់: $200.00</strong>
            </div>
            <div style="clear: both;"></div>
        </div>

      @endforeach
      @else
          <p class="no-data">No data available</p>
      @endif
      <div class="footer-bar"></div>
</body>
</html>

{{-- <!DOCTYPE html>
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
                            <td colspan="2">Total: ${{ number_format($tracking['total']['grand'] ?? 0, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            @endforeach
        @else
            <p class="no-data">No data available</p>
        @endif
    </div>
</body>
</html>



 --}}
