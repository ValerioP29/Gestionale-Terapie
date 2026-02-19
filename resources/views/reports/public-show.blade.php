<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Therapy Report</title>
</head>
<body>
    <h1>Therapy report #{{ $report->id }}</h1>
    <p>Valid until: {{ $report->valid_until?->format('Y-m-d H:i') }}</p>

    @if($pdfUrl)
        <p><a href="{{ route('reports.pdf', ['token' => $report->share_token]) }}">Download PDF</a></p>
    @else
        <p>PDF is still being generated.</p>
    @endif

    <h2>Snapshot</h2>
    <pre>{{ json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</body>
</html>
