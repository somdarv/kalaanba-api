<x-filament-panels::page>
    <x-filament-panels::form wire:submit="previewMatches">
        {{ $this->form }}
        <div class="mt-4 flex gap-2">
            <x-filament::button type="submit" color="gray">Preview matches</x-filament::button>
            <x-filament::button wire:click="replay" color="warning" wire:confirm="Re-emit ALL matching events? This resets delivered_at/attempts.">
                Replay matching events
            </x-filament::button>
        </div>
    </x-filament-panels::form>

    @if ($lastReplayedCount > 0)
        <x-filament::section class="mt-6" heading="Last replay result">
            <p class="text-sm">Re-emit queued for <strong>{{ $lastReplayedCount }}</strong> events.</p>
        </x-filament::section>
    @endif

    @if (count($preview) > 0)
        <x-filament::section class="mt-6" heading="Preview (first 25 matches)">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500">
                        <th class="py-1">Event</th>
                        <th>Occurred at</th>
                        <th>Delivered at</th>
                        <th>Attempts</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($preview as $row)
                        <tr class="border-t border-gray-100 dark:border-gray-700">
                            <td class="py-1">{{ $row['event_name'] }}</td>
                            <td class="font-mono">{{ $row['occurred_at'] }}</td>
                            <td class="font-mono">{{ $row['delivered_at'] ?? 'pending' }}</td>
                            <td>{{ $row['attempts'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
