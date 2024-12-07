<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
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
</head>
<body>
    <div class="header">{{ $title }}</div>
    <p>Date: {{ $date }}</p>
    <div class="content">
        <p>{{ $content }}</p>
    </div>
</body>
</html>
