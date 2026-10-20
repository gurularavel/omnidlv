<x-layouts::app :title="__('Hubs')">
    <x-slot:actions>
        <a href="{{ route('hubs.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" />{{ __('New hub') }}</a>
    </x-slot:actions>

    <p class="mb-5 max-w-[70ch] text-[var(--text-2)]">{{ __('Hubs are your warehouses, stores and pickup points. Every task belongs to one, and couriers start their routes there.') }}</p>

    <div class="plate overflow-hidden">
        @if ($hubs->isEmpty())
            <x-empty icon="warehouse" :title="__('No hubs yet')" :action="route('hubs.create')" :action-label="__('New hub')">
                {{ __('Add the warehouse or store your couriers start from.') }}
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="tbl min-w-[760px]">
                    <thead><tr><th>{{ __('Hub') }}</th><th>{{ __('Address') }}</th><th class="text-right">{{ __('Teams') }}</th><th class="text-right">{{ __('Open tasks') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($hubs as $hub)
                        <tr>
                            <td><span class="flex items-center gap-3"><span class="interchange size-4 border-[3px]"></span>
                                <span><span class="block font-medium">{{ $hub->name }}</span>
                                    @if ($hub->integration_id)<span class="text-[12px] text-[var(--text-3)]">#{{ $hub->integration_id }}</span>@endif</span></span></td>
                            <td class="max-w-[360px] truncate text-[var(--text-2)]">{{ $hub->address }}</td>
                            <td class="text-right">{{ $hub->teams_count }}</td>
                            <td class="text-right"><a href="{{ route('tasks.index', ['hub_id' => $hub->id, 'status_category_slug' => 'created,pool,assigned,in_progress,on_delivery']) }}" class="hover:underline">{{ $hub->open_tasks_count }}</a></td>
                            <td class="text-right"><a href="{{ route('hubs.edit', $hub) }}" class="btn btn-sm">{{ __('Edit') }}</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts::app>
