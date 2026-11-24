<x-cachet::cachet :title="$record->name" page="component">
    <x-cachet::header />

    <main data-slot="main" class="container mx-auto flex max-w-5xl flex-col gap-6 px-4 py-10 sm:px-6 lg:px-8">
        <section data-component="component-detail" class="status-overview flex flex-col">
            <header class="flex flex-col gap-3 border-b border-zinc-900/10 px-4 py-5 dark:border-white/15 sm:flex-row sm:items-start sm:justify-between sm:px-6">
                <div class="flex min-w-0 flex-col gap-1">
                    <h1 data-slot="title" class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $record->name }}</h1>
                    <p class="flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <span data-slot="group">{{ $record->group?->name ?? __('cachet::component.list.ungrouped') }}</span>
                        @foreach ($record->tags as $tag)
                            <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $tag->name }}</span>
                        @endforeach
                    </p>
                </div>

                <span data-slot="status" class="inline-flex shrink-0 items-center gap-2 text-sm font-semibold {{ $status->getTextColorClasses() }}">
                    @svg($status->getIcon(), 'size-5', ['aria-hidden' => 'true'])
                    {{ $status->getLabel() }}
                </span>
            </header>

            @if ($record->description)
                <div data-slot="description" class="prose prose-sm max-w-none border-b border-zinc-900/10 px-4 py-4 dark:prose-invert dark:border-white/15 sm:px-6">
                    {!! $record->formattedDescription() !!}
                </div>
            @endif

            @if ($record->formattedLink())
                <p class="border-b border-zinc-900/10 px-4 py-3 text-sm dark:border-white/15 sm:px-6">
                    <a data-slot="link" href="{{ $record->formattedLink() }}" target="_blank" rel="nofollow noopener" class="font-medium text-accent-content hover:underline">{{ $record->formattedLink() }}</a>
                </p>
            @endif

            <div data-slot="uptime" class="flex flex-col gap-2 px-4 py-4 sm:px-6">
                <div class="flex items-center justify-between text-sm">
                    <h2 class="font-medium text-zinc-900 dark:text-zinc-100">{{ __('cachet::system_status.history_title', ['days' => count($days)]) }}</h2>
                    <span class="tabular-nums text-zinc-500 dark:text-zinc-400">{{ $uptime === null ? __('cachet::system_status.no_data') : __('cachet::system_status.uptime', ['uptime' => number_format($uptime, 2)]) }}</span>
                </div>
                <x-cachet::uptime-bars :days="$days" />
            </div>
        </section>

        @if ($schedules->isNotEmpty())
            <section data-component="component-schedules" class="status-overview flex flex-col">
                <h2 class="border-b border-zinc-900/10 px-4 py-3 font-semibold text-zinc-900 dark:border-white/15 dark:text-zinc-100 sm:px-6">{{ __('cachet::component.detail.schedules') }}</h2>
                <ul class="divide-y divide-zinc-900/10 dark:divide-white/15">
                    @foreach ($schedules as $schedule)
                        <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm sm:px-6">
                            <a href="{{ route('cachet.status-page.schedule', $schedule) }}" class="truncate font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $schedule->name }}</a>
                            <time datetime="{{ $schedule->scheduled_at?->toW3cString() }}" class="shrink-0 text-zinc-500 dark:text-zinc-400">{{ $schedule->scheduled_at?->isoFormat('lll') }}</time>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section data-component="component-incidents" class="status-overview flex flex-col">
            <h2 class="border-b border-zinc-900/10 px-4 py-3 font-semibold text-zinc-900 dark:border-white/15 dark:text-zinc-100 sm:px-6">{{ __('cachet::component.detail.incidents') }}</h2>
            <ul class="divide-y divide-zinc-900/10 dark:divide-white/15">
                @forelse ($incidents as $incident)
                    <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm sm:px-6">
                        <a href="{{ route('cachet.status-page.incident', $incident) }}" class="truncate font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $incident->name }}</a>
                        <span class="flex shrink-0 items-center gap-3 text-zinc-500 dark:text-zinc-400">
                            <span>{{ $incident->status?->getLabel() }}</span>
                            <time datetime="{{ $incident->timestamp->toW3cString() }}">{{ $incident->timestamp->isoFormat('lll') }}</time>
                        </span>
                    </li>
                @empty
                    <li data-slot="empty" class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400 sm:px-6">{{ __('cachet::component.detail.no_incidents') }}</li>
                @endforelse
            </ul>
        </section>

        <section data-component="component-status-changes" class="status-overview flex flex-col">
            <h2 class="border-b border-zinc-900/10 px-4 py-3 font-semibold text-zinc-900 dark:border-white/15 dark:text-zinc-100 sm:px-6">{{ __('cachet::component.detail.status_changes') }}</h2>
            <ul class="divide-y divide-zinc-900/10 dark:divide-white/15">
                @forelse ($statusChanges as $change)
                    <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm sm:px-6">
                        <span class="flex min-w-0 items-center gap-2">
                            <span class="text-zinc-500 dark:text-zinc-400">{{ $change->old_status?->getLabel() }}</span>
                            <x-heroicon-m-arrow-right class="size-3.5 shrink-0 text-zinc-400" aria-hidden="true" />
                            <span class="font-medium {{ $change->new_status->getTextColorClasses() }}">{{ $change->new_status->getLabel() }}</span>
                        </span>
                        <time datetime="{{ $change->created_at?->toW3cString() }}" class="shrink-0 text-zinc-500 dark:text-zinc-400">{{ $change->created_at?->isoFormat('lll') }}</time>
                    </li>
                @empty
                    <li data-slot="empty" class="px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400 sm:px-6">{{ __('cachet::component.detail.no_status_changes') }}</li>
                @endforelse
            </ul>
        </section>

        <nav data-component="page-navigation" class="flex">
            <a href="{{ route('cachet.status-page') }}#components" class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-medium text-zinc-600 ring-1 ring-zinc-900/10 transition hover:bg-white hover:text-zinc-900 dark:text-zinc-400 dark:ring-white/15 dark:hover:bg-zinc-700 dark:hover:text-white">
                <x-heroicon-m-chevron-left class="size-3.5" />
                {{ __('cachet::component.detail.back') }}
            </a>
        </nav>
    </main>

    <x-cachet::footer />
</x-cachet::cachet>
