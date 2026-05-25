<x-filament-panels::page>
    <x-filament-panels::form wire:submit="inject">
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button type="submit" color="warning" wire:confirm="Inject synthetic data into the current environment?">
                Inject
            </x-filament::button>
        </div>
    </x-filament-panels::form>

    @if ($lastResult)
        <x-filament::section class="mt-6" heading="Last result">
            <p class="text-sm">{{ $lastResult }}</p>
        </x-filament::section>
    @endif
</x-filament-panels::page>
