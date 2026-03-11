<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report clinico terapia</title>
    <x-theme.styles context="frontend" />
    @php($theme = \App\Support\ThemeRegistry::resolve('frontend'))
    <style>
        body { margin: 0; padding: 24px; }
        .report-wrap { max-width: 980px; margin: 0 auto; }
        h1 { margin-bottom: 6px; }
        h2 { margin-top: 24px; border-bottom: 1px solid var(--color-border); padding-bottom: 6px; }
        .meta { margin-bottom: 12px; }
        .card { padding: 14px; margin-top: 10px; }
        .row { margin-bottom: 8px; }
        ul { margin: 0; padding-left: 20px; }
        .label { font-size: 0.9rem; color: var(--color-text-muted); font-weight: 600; }
        li { line-height: 1.5; margin-bottom: 6px; }
        @media print { body { padding: 0; } .report-wrap { max-width: none; } }
    </style>
</head>
<body class="{{ $theme['body_class'] }}">
    <main class="report-wrap">
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
    </main>
</body>
</html>
