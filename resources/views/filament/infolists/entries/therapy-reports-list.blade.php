@php($reports = $getState() ?? [])

@if (count($reports) === 0)
    <p class="text-sm text-gray-600">Nessun report generato.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left border-b">
                    <th class="py-2 pr-4">Report</th>
                    <th class="py-2 pr-4">Data generazione</th>
                    <th class="py-2 pr-4">Stato</th>
                    <th class="py-2 pr-4">Download</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($reports as $report)
                    <tr class="border-b align-top">
                        <td class="py-2 pr-4">#{{ $report['id'] }}</td>
                        <td class="py-2 pr-4">{{ $report['generated_at'] }}</td>
                        <td class="py-2 pr-4">
                            {{ $report['status'] }}
                            @if (!empty($report['error_message']))
                                <div class="text-xs text-danger-600 mt-1">{{ $report['error_message'] }}</div>
                            @endif
                        </td>
                        <td class="py-2 pr-4">
                            @if (!empty($report['download_url']))
                                <a href="{{ $report['download_url'] }}" class="text-primary-600 underline" target="_blank" rel="noopener noreferrer">
                                    Scarica
                                </a>
                            @else
                                <span class="text-gray-500">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
