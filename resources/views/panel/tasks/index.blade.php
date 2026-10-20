@php
    $current = $filters['status_category_slug'] ?? null;
    $tabs = [
        ['open', __('Open'), 'created,pool,assigned,in_progress,on_delivery'],
        ['unassigned', __('Unassigned'), 'created,pool'],
        ['moving', __('In motion'), 'assigned,in_progress,on_delivery'],
        ['done', __('Delivered'), 'completed'],
        ['problem', __('Failed and cancelled'), 'failed,cancelled'],
    ];
    $hasFilters = collect($filters)->except(['page', 'status_category_slug'])->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();
@endphp
<x-layouts::app :title="__('Tasks')">
    <x-slot:actions>
        <a href="{{ route('tasks.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" />{{ __('New task') }}</a>
    </x-slot:actions>

    <nav class="scroll-strip -mx-1 mb-5 flex gap-1 overflow-x-auto border-b-[1.5px] border-[var(--hairline)]" aria-label="{{ __('Task views') }}">
        <a href="{{ route('tasks.index', collect($filters)->except(['status_category_slug', 'page'])->all()) }}"
           class="nav-link" @if ($current === null) aria-current="page" @endif>{{ __('All') }}</a>
        @foreach ($tabs as [$key, $label, $value])
            <a href="{{ route('tasks.index', [...collect($filters)->except('page')->all(), 'status_category_slug' => $value]) }}"
               class="nav-link" @if ($current === $value) aria-current="page" @endif>
                {{ $label }} <span class="text-[var(--text-3)]">{{ $tabCounts[$key] }}</span>
            </a>
        @endforeach
    </nav>

    <form method="GET" class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(220px,1fr)_180px_180px_220px_auto]">
        @if ($current)<input type="hidden" name="status_category_slug" value="{{ $current }}">@endif
        <div class="relative">
            <x-icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--text-3)]" />
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="input pl-9" placeholder="{{ __('Tracking code, order no, phone, address') }}" aria-label="{{ __('Search') }}">
        </div>
        <x-select name="hub_id" :options="$hubs" :value="$filters['hub_id'] ?? null" :placeholder="__('All hubs')" aria-label="{{ __('Hub') }}" />
        <select name="courier_id" class="input" aria-label="{{ __('Courier') }}">
            <option value="">{{ __('All couriers') }}</option>
            <option value="none" @selected(($filters['courier_id'] ?? '') === 'none')>{{ __('Without courier') }}</option>
            @foreach ($couriers as $id => $name)
                <option value="{{ $id }}" @selected(($filters['courier_id'] ?? '') == $id)>{{ $name }}</option>
            @endforeach
        </select>
        <input type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="input" aria-label="{{ __('Deadline date') }}">
        <div class="flex gap-2">
            <button class="btn"><x-icon name="filter" :size="16" />{{ __('Filter') }}</button>
            @if ($hasFilters)
                <a href="{{ route('tasks.index', array_filter(['status_category_slug' => $current])) }}" class="btn btn-ghost">{{ __('Reset') }}</a>
            @endif
        </div>
    </form>

    @if (! empty($filters['late']) || ! empty($filters['unresolved']))
        <div class="flash mb-5">
            <x-icon name="triangle-alert" class="mt-0.5 text-ink-amber" />
            <span>{{ ! empty($filters['late']) ? __('Showing tasks past their deadline.') : __('Showing tasks whose address could not be placed on the map.') }}</span>
            <a href="{{ route('tasks.index') }}" class="ml-auto underline">{{ __('Show all') }}</a>
        </div>
    @endif

    <div class="plate overflow-hidden">
        @if ($tasks->isEmpty())
            @if ($hasFilters || $current)
                <x-empty icon="search" :title="__('No tasks match these filters')">{{ __('Try another date or clear the search.') }}</x-empty>
            @else
                <x-empty icon="package" :title="__('No tasks yet')" :action="route('tasks.create')" :action-label="__('New task')">
                    {{ __('Create a task here, import a spreadsheet, or send orders through the API.') }}
                </x-empty>
            @endif
        @else
            <div class="overflow-x-auto">
                <table class="tbl min-w-[980px]">
                    <thead>
                    <tr>
                        <th class="w-[88px]">{{ __('Min. left') }}</th>
                        <th>{{ __('Task') }}</th>
                        <th>{{ __('Recipient') }}</th>
                        <th>{{ __('Courier') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Window') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($tasks as $task)
                        <tr class="cursor-pointer" onclick="if(!event.target.closest('a'))location.href='{{ route('tasks.show', $task) }}'">
                            <td><x-minutes :ends-at="$task->ends_at" :final="$task->isFinal()" /></td>
                            <td>
                                <a href="{{ route('tasks.show', $task) }}" class="t-code hover:underline">{{ $task->tracking_code }}</a>
                                <span class="mt-0.5 flex items-center gap-2 text-[12px] text-[var(--text-3)]">
                                    @if ($task->type_slug->value === 'pickup')<span class="tag h-5">{{ __('Pickup') }}</span>@endif
                                    @if ($task->is_locked)<x-icon name="lock" :size="13" />@endif
                                    {{ $task->integration_id ? '#'.$task->integration_id.' · ' : '' }}{{ $task->hub->name }}
                                </span>
                            </td>
                            <td class="max-w-[320px]">
                                <span class="block truncate">{{ $task->destination_name ?? '—' }}</span>
                                <span class="flex items-center gap-1.5 truncate text-[13px] text-[var(--text-2)]">
                                    @unless ($task->is_destination_resolved)<x-icon name="circle-alert" :size="13" class="shrink-0 text-ink-amber" />@endunless
                                    {{ $task->destination_address }}
                                </span>
                            </td>
                            <td class="text-[var(--text-2)]">{{ $task->courier?->name ?? '—' }}</td>
                            <td><x-status :category="$task->status_category_slug" :label="$task->status->label()" /></td>
                            <td class="whitespace-nowrap text-[13px] text-[var(--text-2)]">
                                {{ \App\Support\Ui::localTime($task->starts_at, 'd M H:i') }}–{{ \App\Support\Ui::localTime($task->ends_at) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[var(--hairline)] px-4 py-3">{{ $tasks->links() }}</div>
        @endif
    </div>
</x-layouts::app>
