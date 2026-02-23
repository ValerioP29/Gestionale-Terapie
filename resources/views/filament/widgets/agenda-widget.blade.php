<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-3">
            @foreach (['inRitardo' => 'In ritardo', 'oggi' => 'Oggi', 'prossimi' => 'Prossimi (7 giorni)'] as $key => $label)
                <div>
                    <h3 class="font-semibold text-sm mb-2">{{ $label }}</h3>
                    <ul class="space-y-2 text-sm">
                        @forelse(${$key} as $item)
                            <li class="rounded-lg border border-gray-200 p-2">
                                <a href="{{ $item['url'] }}" class="block hover:bg-gray-50 rounded">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-medium text-gray-900">{{ $item['patient_name'] }}</span>
                                        <span class="text-xs text-gray-500">{{ $item['scheduled_at_label'] }}</span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-2 text-xs">
                                        <span class="inline-flex rounded bg-primary-50 px-2 py-0.5 text-primary-700">{{ $item['item_type'] }}</span>
                                        <span class="inline-flex rounded bg-gray-100 px-2 py-0.5 text-gray-700">{{ $item['status_label'] }}</span>
                                    </div>
                                    <p class="mt-1 text-gray-700">{{ $item['title'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $item['subtitle'] }}</p>
                                </a>
                            </li>
                        @empty
                            <li class="text-gray-500">Nessuna voce in agenda</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
