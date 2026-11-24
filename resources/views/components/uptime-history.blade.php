<section data-component="uptime-history" class="status-overview flex flex-col">
    <header class="border-b border-zinc-900/10 px-4 py-4 dark:border-white/15 sm:px-6">
        <h2 data-slot="title" class="font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ __('cachet::system_status.history_title', ['days' => $days]) }}</h2>
    </header>

    @if ($rows->isEmpty())
        <p data-slot="empty" class="px-4 py-6 text-sm text-zinc-500 dark:text-zinc-400 sm:px-6">{{ __('cachet::system_status.history_empty') }}</p>
    @else
        <ul data-slot="list" class="divide-y divide-zinc-900/10 dark:divide-white/15">
            @foreach ($rows as $row)
                <li data-slot="row" @if ($row['group']) data-component-group-id="{{ $row['group'] }}" @endif class="flex flex-col gap-2 px-4 py-3 sm:px-6">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        @if ($row['group'])
                            <a href="{{ route('cachet.status-page', ['group' => $row['group']]) }}#components" class="truncate font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $row['label'] }}</a>
                        @else
                            <span class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $row['label'] }}</span>
                        @endif
                        <span data-slot="uptime" class="shrink-0 tabular-nums text-zinc-500 dark:text-zinc-400">
                            {{ $row['uptime'] === null ? __('cachet::system_status.no_data') : __('cachet::system_status.uptime', ['uptime' => number_format($row['uptime'], 2)]) }}
                        </span>
                    </div>
                    <x-cachet::uptime-bars :days="$row['days']" />
                </li>
            @endforeach
        </ul>
    @endif
</section>
