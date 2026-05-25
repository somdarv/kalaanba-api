<x-filament-panels::page>
    <x-filament-panels::form wire:submit="search">
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button type="submit">Inspect</x-filament::button>
        </div>
    </x-filament-panels::form>

    @if ($foundUser)
        <x-filament::section class="mt-6" heading="User">
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <dt class="font-medium">ID</dt>
                <dd class="font-mono">{{ $foundUser->getAuthIdentifier() }}</dd>
                <dt class="font-medium">Email</dt>
                <dd>{{ $foundUser->email }}</dd>
                <dt class="font-medium">Name</dt>
                <dd>{{ $foundUser->name }}</dd>
                <dt class="font-medium">Role</dt>
                <dd>{{ $foundUser->role?->value ?? '—' }}</dd>
                <dt class="font-medium">Archived at</dt>
                <dd>{{ $foundUser->archived_at?->toIso8601String() ?? 'active' }}</dd>
                <dt class="font-medium">Last seen at</dt>
                <dd>{{ $foundUser->last_seen_at?->toIso8601String() ?? 'never' }}</dd>
                <dt class="font-medium">Created at</dt>
                <dd>{{ $foundUser->created_at?->toIso8601String() }}</dd>
            </dl>
        </x-filament::section>

        <x-filament::section class="mt-6" heading="Recent audit entries">
            @if (count($recentAuditEntries) === 0)
                <p class="text-sm text-gray-500">No audit-log activity recorded for this actor.</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500">
                            <th class="py-1">When</th>
                            <th>Method</th>
                            <th>Path</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentAuditEntries as $entry)
                            <tr class="border-t border-gray-100 dark:border-gray-700">
                                <td class="py-1 font-mono">{{ $entry['occurred_at'] }}</td>
                                <td>{{ $entry['method'] }}</td>
                                <td class="truncate">{{ $entry['path'] }}</td>
                                <td>{{ $entry['response_status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    @elseif (!empty($data['query'] ?? null))
        <x-filament::section class="mt-6" heading="No match">
            <p class="text-sm text-gray-500">No user found for query: <span class="font-mono">{{ $data['query'] }}</span></p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
