<x-layouts::app :title="__('Teams')">
    <x-slot:actions>
        <a href="{{ route('teams.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" />{{ __('New team') }}</a>
    </x-slot:actions>

    <p class="mb-5 max-w-[70ch] text-[var(--text-2)]">{{ __('A team is a line on the map: its couriers share a colour, and its zone decides which orders it takes.') }}</p>

    <div class="plate overflow-hidden">
        @if ($teams->isEmpty())
            <x-empty icon="users" :title="__('No teams yet')" :action="route('teams.create')" :action-label="__('New team')">
                {{ __('Group couriers by shift, vehicle or district, and draw the zone each team serves.') }}
            </x-empty>
        @else
            <ul>
                @foreach ($teams as $team)
                    <li class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 border-b border-[var(--hairline)] px-5 py-4 last:border-0 sm:grid-cols-[220px_minmax(0,1fr)_auto]">
                        <span class="min-w-0">
                            <span class="block font-medium">{{ $team->name }}</span>
                            <span class="text-[13px] text-[var(--text-3)]">{{ $team->hub?->name ?? __('All hubs') }}</span>
                        </span>
                        <span class="hidden items-center gap-3 sm:flex">
                            <span class="h-[6px] flex-1 rounded-full" style="background: {{ $team->color }}"></span>
                            <span class="t-label w-[150px] text-right">{{ trans_choice(':count courier|:count couriers', $team->couriers_count) }}{{ $team->polygon ? ' · '.__('zone') : '' }}</span>
                        </span>
                        <a href="{{ route('teams.edit', $team) }}" class="btn btn-sm">{{ __('Edit') }}</a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts::app>
