<?php

namespace Cachet\Filament\Components;

use Closure;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;

/**
 * A component picker that searches on the server.
 *
 * Nothing is preloaded: typing runs a capped query, and only the labels of
 * the selected components are fetched when a form is filled.
 */
class ComponentSelect extends Select
{
    /**
     * The record whose attached components are left out of the results.
     */
    protected Model|Closure|null $excludeAttachedTo = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->native(false)
            ->searchable()
            ->searchDebounce(300)
            ->optionsLimit(ComponentOptions::SEARCH_LIMIT)
            ->searchPrompt(__('cachet::component.select.search_prompt'))
            ->noSearchResultsMessage(__('cachet::component.select.no_results'))
            ->getSearchResultsUsing(fn (ComponentSelect $component, string $search): array => ComponentOptions::search($search, $component->getExcludedIds()))
            ->getOptionLabelUsing(fn (ComponentSelect $component, mixed $value): ?string => ComponentOptions::labels([$value], $component->getExcludedIds())[$value] ?? null)
            ->getOptionLabelsUsing(fn (ComponentSelect $component, array $values): array => ComponentOptions::labels($values, $component->getExcludedIds()));
    }

    /**
     * Leave out the components already attached to the given record.
     */
    public function excludeAttachedTo(Model|Closure|null $record): static
    {
        $this->excludeAttachedTo = $record;

        return $this;
    }

    /**
     * @return array<int, int>
     */
    protected function getExcludedIds(): array
    {
        $record = $this->evaluate($this->excludeAttachedTo);

        if (! $record instanceof Model || ! method_exists($record, 'components')) {
            return [];
        }

        return $record->components()->pluck('components.id')->all();
    }
}
