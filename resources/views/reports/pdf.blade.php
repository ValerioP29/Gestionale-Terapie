<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Report clinico terapia</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        h1 { font-size: 20px; margin: 0 0 8px 0; }
        h2 { font-size: 14px; margin: 20px 0 8px 0; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; }
        .meta { font-size: 11px; color: #4b5563; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        th { background: #f9fafb; text-align: left; width: 32%; }
        .muted { color: #6b7280; }
        .pill { display: inline-block; border: 1px solid #d1d5db; border-radius: 999px; padding: 2px 8px; font-size: 11px; margin-right: 4px; }
        ul { margin: 6px 0 0 16px; padding: 0; }
        li { margin-bottom: 6px; }
    </style>
</head>
<body>
    <h1>Report clinico di adesione alla terapia</h1>
    <div class="meta">
        Report n. {{ $presented['meta']['report_number'] }} · Generato: {{ $presented['meta']['generated_at_rome'] }} · Valido fino: {{ $presented['meta']['valid_until_rome'] }}
    </div>

    <h2>Intestazione farmacia</h2>
    <table>
        @foreach($presented['header'] as $row)
            <tr><th>{{ $row['label'] }}</th><td>{!! nl2br(e($row['value'])) !!}</td></tr>
        @endforeach
    </table>

    <h2>Anagrafica paziente</h2>
    <table>
        @foreach($presented['patient'] as $row)
            <tr><th>{{ $row['label'] }}</th><td>{!! nl2br(e($row['value'])) !!}</td></tr>
        @endforeach
    </table>

    <h2>Terapia e percorso</h2>
    <table>
        @foreach($presented['therapy'] as $row)
            <tr><th>{{ $row['label'] }}</th><td>{!! nl2br(e($row['value'])) !!}</td></tr>
        @endforeach
    </table>

    <h2>Quadro clinico / presa in carico</h2>
    <table>
        @foreach($presented['clinical'] as $row)
            <tr><th>{{ $row['label'] }}</th><td>{!! nl2br(e($row['value'])) !!}</td></tr>
        @endforeach
    </table>

    <h2>Checklist sintetica</h2>
    @if(count($presented['checklist']) > 0)
        <table>
            <thead>
                <tr>
                    <th>Domanda</th>
                    <th>Risposta</th>
                    <th>Compilata il</th>
                </tr>
            </thead>
            <tbody>
                @foreach($presented['checklist'] as $row)
                    <tr>
                        <td>{{ $row['question'] }}</td>
                        <td>{{ $row['answer'] }}</td>
                        <td>{{ $row['answered_at'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">Nessuna risposta checklist disponibile.</p>
    @endif

    <h2>Check / follow-up recenti</h2>
    @if(count($presented['timeline']) > 0)
        <ul>
            @foreach($presented['timeline'] as $row)
                <li>
                    <span class="pill">{{ $row['tipo'] }}</span>
                    <span class="pill">{{ $row['stato'] }}</span><br>
                    Esecuzione: {{ $row['data_esecuzione'] }} · Pianificata: {{ $row['data_pianificata'] }} · Rischio: {{ $row['rischio'] }}<br>
                    <span class="muted">{{ $row['note'] }}</span>
                </li>
            @endforeach
        </ul>
    @else
        <p class="muted">Nessun check o follow-up disponibile.</p>
    @endif

    <h2>Reminder / prossime azioni</h2>
    @if(count($presented['reminders']) > 0)
        <ul>
            @foreach($presented['reminders'] as $row)
                <li>
                    <strong>{{ $row['titolo'] }}</strong> · {{ $row['frequenza'] }} · {{ $row['stato'] }}<br>
                    Prossima scadenza: {{ $row['prossima_scadenza'] }}
                </li>
            @endforeach
        </ul>
    @else
        <p class="muted">Nessun promemoria attivo disponibile.</p>
    @endif

    <h2>Consensi</h2>
    <table>
        @foreach($presented['consents'] as $row)
            <tr><th>{{ $row['label'] }}</th><td>{!! nl2br(e($row['value'])) !!}</td></tr>
        @endforeach
    </table>
</body>
</html>
