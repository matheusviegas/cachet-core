@use('Cachet\Enums\ComponentListSortEnum')
@use('Cachet\Enums\ComponentListViewEnum')
@use('Cachet\Enums\ComponentStatusEnum')

@php($listUrl = fn (array $overrides = []): string => route('cachet.status-page', $filters->parameters($overrides)).'#components')

<div id="components" data-component="component-explorer" class="status-components flex flex-col">
    {{-- Status summary: counts by effective status, each one a filter. --}}
    <nav data-slot="summary" aria-label="{{ __('cachet::component.explorer.summary_label') }}" class="flex flex-wrap gap-2 border-b border-zinc-900/10 px-4 py-4 dark:border-white/15 sm:px-6">
        <a href="{{ $listUrl(['status' => null]) }}"
           data-status="all"
           @if ($filters->statuses === []) aria-current="true" @endif
           @class([
               'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium ring-1 ring-inset transition',
               'bg-zinc-900 text-white ring-zinc-900 dark:bg-white dark:text-zinc-900 dark:ring-white' => $filters->statuses === [],
               'text-zinc-700 ring-zinc-900/15 hover:bg-zinc-900/5 dark:text-zinc-300 dark:ring-white/20 dark:hover:bg-white/10' => $filters->statuses !== [],
           ])>
            {{ __('cachet::component.explorer.all') }}
            <span data-slot="count" class="tabular-nums">{{ number_format($total) }}</span>
        </a>

        @foreach (ComponentStatusEnum::cases() as $status)
            @continue($counts[$status->name] === 0 && ! $filters->hasStatus($status))

            <a href="{{ $listUrl(['status' => $filters->hasStatus($status) ? null : $status->name]) }}"
               data-status="{{ $status->name }}"
               @if ($filters->hasStatus($status)) aria-current="true" @endif
               @class([
                   'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium ring-1 ring-inset transition',
                   'bg-zinc-900 text-white ring-zinc-900 dark:bg-white dark:text-zinc-900 dark:ring-white' => $filters->hasStatus($status),
                   'text-zinc-700 ring-zinc-900/15 hover:bg-zinc-900/5 dark:text-zinc-300 dark:ring-white/20 dark:hover:bg-white/10' => ! $filters->hasStatus($status),
               ])>
                <span class="size-2.5 shrink-0 rounded-full {{ $status->getBackgroundColorClasses() }}" aria-hidden="true"></span>
                {{ $status->getLabel() }}
                <span data-slot="count" class="tabular-nums">{{ number_format($counts[$status->name]) }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Filters: a plain GET form, so it works without JavaScript and every result has a shareable URL. --}}
    <form data-slot="filters" method="get" action="{{ route('cachet.status-page') }}#components" role="search" class="flex flex-col gap-3 border-b border-zinc-900/10 px-4 py-4 dark:border-white/15 sm:flex-row sm:flex-wrap sm:items-end sm:px-6">
        @if ($filters->statuses !== [])
            <input type="hidden" name="status" value="{{ $filters->parameters()['status'] }}" />
        @endif
        @if ($filters->view !== ComponentListViewEnum::list)
            <input type="hidden" name="view" value="{{ $filters->view->value }}" />
        @endif

        <label class="flex min-w-0 flex-1 flex-col gap-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">
            {{ __('cachet::component.explorer.search_label') }}
            <input type="search" name="q" value="{{ $filters->search }}" maxlength="100" placeholder="{{ __('cachet::component.explorer.search_placeholder') }}"
                   class="rounded-md border-0 bg-white px-3 py-2 text-sm text-zinc-900 ring-1 ring-inset ring-zinc-900/15 placeholder:text-zinc-400 focus:ring-2 focus:ring-accent dark:bg-white/5 dark:text-zinc-100 dark:ring-white/20" />
        </label>

        @if ($groups->isNotEmpty())
            <label class="flex flex-col gap-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                {{ __('cachet::component.list.headers.group') }}
                <select name="group" x-data x-on:change="$el.form.requestSubmit()"
                        class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm text-zinc-900 ring-1 ring-inset ring-zinc-900/15 focus:ring-2 focus:ring-accent dark:bg-zinc-900 dark:text-zinc-100 dark:ring-white/20">
                    <option value="">{{ __('cachet::component.explorer.all_groups') }}</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" @selected($filters->group === $group->id)>{{ $group->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if ($tags->isNotEmpty())
            <label class="flex flex-col gap-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                {{ __('cachet::component.list.filters.tags') }}
                <select name="tag" x-data x-on:change="$el.form.requestSubmit()"
                        class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm text-zinc-900 ring-1 ring-inset ring-zinc-900/15 focus:ring-2 focus:ring-accent dark:bg-zinc-900 dark:text-zinc-100 dark:ring-white/20">
                    <option value="">{{ __('cachet::component.explorer.all_tags') }}</option>
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->slug }}" @selected($filters->tag === $tag->slug)>{{ $tag->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @if ($filters->view === ComponentListViewEnum::list)
            <label class="flex flex-col gap-1 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                {{ __('cachet::component.explorer.sort_label') }}
                <select name="sort" x-data x-on:change="$el.form.requestSubmit()"
                        class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm text-zinc-900 ring-1 ring-inset ring-zinc-900/15 focus:ring-2 focus:ring-accent dark:bg-zinc-900 dark:text-zinc-100 dark:ring-white/20">
                    @foreach (ComponentListSortEnum::cases() as $sort)
                        <option value="{{ $sort->value }}" @selected($filters->sort === $sort)>{{ $sort->getLabel() }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <div class="flex items-center gap-2">
            <button type="submit" class="inline-flex min-h-9 items-center rounded-md bg-accent px-3 py-1.5 text-sm font-semibold text-accent-foreground shadow-sm transition hover:opacity-90">
                {{ __('cachet::component.explorer.apply') }}
            </button>
            @if ($filters->isFiltered())
                <a data-slot="clear" href="{{ route('cachet.status-page', $filters->view === ComponentListViewEnum::list ? [] : ['view' => $filters->view->value]) }}#components" class="text-sm font-medium text-zinc-600 underline-offset-2 hover:underline dark:text-zinc-400">
                    {{ __('cachet::component.explorer.clear') }}
                </a>
            @endif
        </div>

        <div data-slot="view" role="group" aria-label="{{ __('cachet::component.explorer.view_label') }}" class="flex items-center gap-1 rounded-md p-0.5 ring-1 ring-inset ring-zinc-900/15 dark:ring-white/20 sm:ml-auto">
            @foreach (ComponentListViewEnum::cases() as $view)
                <a href="{{ $listUrl(['view' => $view === ComponentListViewEnum::list ? null : $view->value]) }}"
                   @if ($filters->view === $view) aria-current="true" @endif
                   @class([
                       'rounded px-2.5 py-1 text-sm font-medium transition',
                       'bg-zinc-900/10 text-zinc-900 dark:bg-white/15 dark:text-white' => $filters->view === $view,
                       'text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-200' => $filters->view !== $view,
                   ])>{{ $view->getLabel() }}</a>
            @endforeach
        </div>
    </form>

    @if ($filters->view === ComponentListViewEnum::grid)
        @include('cachet::components.component-grid')
    @elseif ($paginator->isEmpty())
        <p data-slot="empty" class="px-4 py-8 text-center text-sm text-zinc-500 dark:text-zinc-400 sm:px-6">{{ __('cachet::component.explorer.empty') }}</p>
    @else
        <ul data-slot="list" class="divide-y divide-zinc-900/10 dark:divide-white/15">
            @foreach ($paginator as $component)
                @php($status = $component->latest_status)
                <li data-component="component" data-component-id="{{ $component->getKey() }}" class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-3 sm:px-6">
                    <div class="flex min-w-0 items-center gap-2">
                        @svg($status->getIcon(), 'size-4 shrink-0 '.$status->getTextColorClasses(), ['data-slot' => 'status-icon', 'aria-hidden' => 'true'])
                        <a data-slot="title" href="{{ route('cachet.status-page.component', $component) }}" class="truncate font-medium text-zinc-900 hover:underline dark:text-zinc-100">{{ $component->name }}</a>
                        @if ($component->group)
                            <span data-slot="group" class="hidden shrink-0 text-sm text-zinc-500 dark:text-zinc-400 sm:inline">{{ $component->group->name }}</span>
                        @endif
                        @if ($showTags && $component->tags->isNotEmpty())
                            <span data-slot="tags" class="hidden gap-1 md:flex">
                                @foreach ($component->tags as $tag)
                                    <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $tag->name }}</span>
                                @endforeach
                            </span>
                        @endif
                    </div>

                    @if ($component->impacting_incident)
                        <a data-slot="status" href="{{ route('cachet.status-page.incident', $component->impacting_incident) }}" class="shrink-0 pl-6 text-sm font-semibold sm:pl-0 {{ $status->getTextColorClasses() }}">{{ $status->getLabel() }}</a>
                    @else
                        <span data-slot="status" class="shrink-0 pl-6 text-sm font-semibold sm:pl-0 {{ $status->getTextColorClasses() }}">{{ $status->getLabel() }}</span>
                    @endif
                </li>
            @endforeach
        </ul>

        <nav data-slot="pagination" aria-label="{{ __('cachet::component.explorer.pagination_label') }}" class="flex items-center justify-between gap-3 border-t border-zinc-900/10 px-4 py-3 text-sm text-zinc-500 dark:border-white/15 dark:text-zinc-400 sm:px-6">
            <span>{{ __('cachet::component.explorer.showing', ['from' => number_format($paginator->firstItem()), 'to' => number_format($paginator->lastItem()), 'total' => number_format($paginator->total())]) }}</span>
            <span class="flex gap-2">
                @if ($paginator->onFirstPage())
                    <span class="rounded-md px-3 py-1.5 opacity-50" aria-disabled="true">{{ __('cachet::component.explorer.previous') }}</span>
                @else
                    <a rel="prev" href="{{ $paginator->previousPageUrl() }}#components" class="rounded-md px-3 py-1.5 font-medium text-zinc-700 ring-1 ring-inset ring-zinc-900/15 hover:bg-zinc-900/5 dark:text-zinc-200 dark:ring-white/20">{{ __('cachet::component.explorer.previous') }}</a>
                @endif
                @if ($paginator->hasMorePages())
                    <a rel="next" href="{{ $paginator->nextPageUrl() }}#components" class="rounded-md px-3 py-1.5 font-medium text-zinc-700 ring-1 ring-inset ring-zinc-900/15 hover:bg-zinc-900/5 dark:text-zinc-200 dark:ring-white/20">{{ __('cachet::component.explorer.next') }}</a>
                @else
                    <span class="rounded-md px-3 py-1.5 opacity-50" aria-disabled="true">{{ __('cachet::component.explorer.next') }}</span>
                @endif
            </span>
        </nav>
    @endif
</div>
