<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Therapy Report PDF</title>
</head>
<body>
    <h1>Therapy report #{{ $report->id }}</h1>
    <p>Generated at: {{ now()->format('Y-m-d H:i') }}</p>
    <pre style="font-size: 11px">{{ json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</body>
</html>
