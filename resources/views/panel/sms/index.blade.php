<x-layouts::app :title="__('SMS log')">
    @if ($demo)
        <div class="flash mb-5">
            <x-icon name="circle-alert" class="mt-0.5 text-ink-amber" />
            <span>{{ __('SMS runs in demo mode: nothing is sent to phones. Every message that would go out is listed here, so flows can be tested end to end.') }}</span>
        </div>
    @endif

    <div class="plate overflow-hidden">
        @if ($messages->isEmpty())
            <x-empty icon="message-square-text" :title="__('No messages yet')">{{ __('Delivery codes and courier sign-in codes appear here once they are sent.') }}</x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="tbl min-w-[820px]">
                    <thead><tr><th>{{ __('Time') }}</th><th>{{ __('To') }}</th><th>{{ __('Purpose') }}</th><th>{{ __('Text') }}</th><th>{{ __('Status') }}</th></tr></thead>
                    <tbody>
                    @foreach ($messages as $sms)
                        <tr>
                            <td class="whitespace-nowrap text-[var(--text-2)]">{{ \App\Support\Ui::localTime($sms->created_at, 'd M H:i') }}</td>
                            <td class="whitespace-nowrap">{{ $sms->to }}</td>
                            <td>
                                {{ __('sms.purpose.'.$sms->purpose) }}
                                @if ($sms->task)<a href="{{ route('tasks.show', $sms->task_id) }}" class="t-code block text-[12px] text-[var(--text-2)] hover:underline">{{ $sms->task->tracking_code }}</a>@endif
                            </td>
                            <td class="max-w-[420px] text-[var(--text-2)]">{{ $sms->body }}</td>
                            <td>
                                <span class="status" data-form="{{ $sms->status === 'failed' ? 'double' : 'solid' }}" style="--status-ink: {{ $sms->status === 'failed' ? '#e21d2d' : '#c3c9da' }}">
                                    {{ __('sms.status.'.$sms->status) }}{{ $sms->driver === 'demo' ? ' · demo' : '' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-[var(--hairline)] px-4 py-3">{{ $messages->links() }}</div>
        @endif
    </div>
</x-layouts::app>
