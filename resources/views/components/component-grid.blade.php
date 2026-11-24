@use('Cachet\Enums\ComponentStatusEnum')

{{-- One plain link per component: no Alpine and no tooltips to initialise, so thousands of tiles stay cheap. --}}
<div data-slot="grid" class="flex flex-col gap-5 px-4 py-4 sm:px-6">
    <ul data-slot="legend" class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
        @foreach (ComponentStatusEnum::cases() as $status)
            <li class="flex items-center gap-1.5">
                <span class="size-3 rounded-sm {{ $status->getBackgroundColorClasses() }}" aria-hidden="true"></span>
                {{ $status->getLabel() }}
            </li>
        @endforeach
    </ul>

    @forelse ($tiles->groupBy(fn ($component) => $component->component_group_id ?? 0) as $groupId => $groupTiles)
        <section data-slot="grid-group" data-component-group-id="{{ $groupId ?: '' }}" class="flex flex-col gap-2">
            <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                {{ $groupTiles->first()->group?->name ?? __('cachet::component.list.ungrouped') }}
                <span class="font-normal text-zinc-500 dark:text-zinc-400">({{ number_format($groupTiles->count()) }})</span>
            </h3>
            <div class="flex flex-wrap gap-1">
                @foreach ($groupTiles as $component)
                    @php($status = $statuses->of($component))
                    <a data-slot="tile"
                       data-status="{{ $status->name }}"
                       href="{{ route('cachet.status-page.component', $component) }}"
                       title="{{ $component->name }} · {{ $status->getLabel() }}"
                       aria-label="{{ $component->name }}: {{ $status->getLabel() }}"
                       class="block size-4 rounded-sm transition hover:scale-125 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-accent {{ $status->getBackgroundColorClasses() }}"></a>
                @endforeach
            </div>
        </section>
    @empty
        <p data-slot="empty" class="py-4 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ __('cachet::component.explorer.empty') }}</p>
    @endforelse

    @if ($truncated)
        <p data-slot="truncated" class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('cachet::component.explorer.grid_truncated', ['limit' => number_format($gridLimit)]) }}</p>
    @endif
</div>
