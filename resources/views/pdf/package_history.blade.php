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
    </style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body>
    <div class="header">{{ $title ?? 'History'}}</div>
    <p>Date: {{ $date ?? 'date' }}</p>
    <div class="content">
        <p>
            @foreach([1, 2, 3] as $value)
                <h4>Tracking Number :{{ 1241200004 }}</h4>

                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Merchant</th>
                                <th scope="col">Receiver Phone</th>
                                <th scope="col">Receiver Address</th>
                                <th scope="col">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach([1, 2, 3] as $idx => $value)
                            <tr>
                                <th scope="row">{{$idx + 1}}</th>
                                <td>
                                    <p>0958672383</p>
                                    <small>Merchant Name</small>
                                </td>
                                <td>092847473</td>
                                <td>#4 st.360 Norodom</td>
                                <td>$10</td>
                            </tr>
                            @endforeach
                        </tbody>
                        </table>


            @endforeach
        </p>
    </div>
</body>
</html>
