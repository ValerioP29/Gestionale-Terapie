<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Therapy Report PDF</title>
</head>
<body>
    <h1>Therapy report #{{ $report->id }}</h1>
    <p>Generato il (Europe/Rome): {{ $generatedAtRome }}</p>
    <p>Valido fino al (Europe/Rome): {{ $validUntilRome ?? '-' }}</p>
    <pre style="font-size: 11px">{{ json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</body>
</html>
