@props([
    'days',
])

{{-- One span per day with a native title tooltip: cheap enough to render for every group. --}}
<div data-slot="bars" class="grid h-6 gap-px" style="grid-template-columns: repeat({{ count($days) }}, minmax(1px, 1fr))">
    @foreach ($days as $date => $status)
        @php($label = \Illuminate\Support\Carbon::parse($date)->isoFormat('ll').': '.($status?->getLabel() ?? __('cachet::system_status.no_data')))
        <span data-slot="day" data-date="{{ $date }}" data-status="{{ $status?->name ?? 'none' }}" title="{{ $label }}" aria-label="{{ $label }}"
              class="block rounded-[1px] {{ $status?->getBackgroundColorClasses() ?? 'bg-zinc-100 dark:bg-zinc-800' }}"></span>
    @endforeach
</div>
