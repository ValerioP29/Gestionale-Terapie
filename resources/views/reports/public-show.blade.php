<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report clinico terapia</title>
    <style>
        body { font-family: Inter, system-ui, -apple-system, sans-serif; margin: 24px; color: #111827; }
        h1 { margin-bottom: 6px; }
        h2 { margin-top: 24px; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; }
        .meta { color: #4b5563; margin-bottom: 12px; }
        .card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px; margin-top: 10px; }
        .row { margin-bottom: 6px; }
        .label { color: #6b7280; font-size: 0.9rem; }
    </style>
</head>
<body>
    <h1>Report clinico di adesione alla terapia</h1>
    <p class="meta">
        Report {{ $presented['meta']['report_number'] }} · Generato: {{ $presented['meta']['generated_at_rome'] }} · Valido fino: {{ $presented['meta']['valid_until_rome'] }}
    </p>

    @if($pdfUrl)
        <p><a href="{{ route('reports.pdf', ['token' => $report->share_token]) }}">Scarica PDF</a></p>
    @else
        <p>PDF in generazione, riprova tra pochi secondi.</p>
    @endif

    <h2>Paziente</h2>
    <div class="card">
        @foreach($presented['patient'] as $row)
            <div class="row"><span class="label">{{ $row['label'] }}:</span> {{ $row['value'] }}</div>
        @endforeach
    </div>

    <h2>Terapia e quadro clinico</h2>
    <div class="card">
        @foreach($presented['therapy'] as $row)
            <div class="row"><span class="label">{{ $row['label'] }}:</span> {{ $row['value'] }}</div>
        @endforeach
        <hr>
        @foreach($presented['clinical'] as $row)
            <div class="row"><span class="label">{{ $row['label'] }}:</span> {{ $row['value'] }}</div>
        @endforeach
    </div>

    <h2>Check e follow-up recenti</h2>
    <div class="card">
        @if(count($presented['timeline']) > 0)
            <ul>
                @foreach($presented['timeline'] as $row)
                    <li>{{ $row['tipo'] }} ({{ $row['stato'] }}) - {{ $row['data_esecuzione'] }} - {{ $row['note'] }}</li>
                @endforeach
            </ul>
        @else
            <p>Nessun evento disponibile.</p>
        @endif
    </div>
</body>
</html>
