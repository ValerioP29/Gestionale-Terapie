<x-filament-widgets::widget>
    <x-filament::section>
        <div class="grid gap-4 md:grid-cols-3">
            @foreach (['overdue' => 'Overdue', 'today' => 'Today', 'upcoming' => 'Upcoming (7 giorni)'] as $key => $label)
                <div>
                    <h3 class="font-semibold text-sm mb-2">{{ $label }}</h3>
                    <ul class="space-y-1 text-sm">
                        @forelse(${$key} as $reminder)
                            <li>
                                <span class="font-medium">{{ $reminder->title }}</span>
                                <span class="text-gray-500">{{ ($reminder->next_due_at ?? $reminder->first_due_at)?->format('Y-m-d H:i') }}</span>
                            </li>
                        @empty
                            <li class="text-gray-500">Nessun promemoria</li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
