<?php

namespace Cachet\View;

use Cachet\Enums\ComponentListSortEnum;
use Cachet\Enums\ComponentListViewEnum;
use Cachet\Enums\ComponentStatusEnum;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The filters of the status page component list, read from the query string.
 *
 * Invalid values are dropped rather than rejected, so a stale or edited link
 * still renders the page. Statuses use their names so links stay readable:
 *
 *   /?q=invoice&status=major_outage,partial_outage&group=3&tag=finance&sort=name&view=grid
 */
final class ComponentListFilters
{
    /**
     * The longest search term that is used.
     */
    private const SEARCH_MAX_LENGTH = 100;

    /**
     * @param  list<ComponentStatusEnum>  $statuses
     */
    public function __construct(
        public readonly string $search = '',
        public readonly array $statuses = [],
        public readonly ?int $group = null,
        public readonly ?string $tag = null,
        public readonly ComponentListSortEnum $sort = ComponentListSortEnum::severity,
        public readonly ComponentListViewEnum $view = ComponentListViewEnum::list,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $statuses = collect(explode(',', self::string($request, 'status')))
            ->map(fn (string $name): ?ComponentStatusEnum => collect(ComponentStatusEnum::cases())->first(fn (ComponentStatusEnum $status): bool => $status->name === trim($name)))
            ->filter()
            ->unique(fn (ComponentStatusEnum $status): int => $status->value)
            ->values()
            ->all();

        $group = self::string($request, 'group');
        $tag = Str::slug(self::string($request, 'tag'));

        return new self(
            search: Str::limit(trim(self::string($request, 'q')), self::SEARCH_MAX_LENGTH, ''),
            statuses: $statuses,
            group: ctype_digit($group) ? (int) $group : null,
            tag: $tag !== '' ? $tag : null,
            sort: ComponentListSortEnum::tryFrom(self::string($request, 'sort')) ?? ComponentListSortEnum::severity,
            view: ComponentListViewEnum::tryFrom(self::string($request, 'view')) ?? ComponentListViewEnum::list,
        );
    }

    /**
     * Determine whether the given status is one of the selected ones.
     */
    public function hasStatus(ComponentStatusEnum $status): bool
    {
        return in_array($status, $this->statuses, true);
    }

    /**
     * Determine whether any filter narrows the components shown.
     */
    public function isFiltered(): bool
    {
        return $this->search !== '' || $this->statuses !== [] || $this->group !== null || $this->tag !== null;
    }

    /**
     * Get the query string parameters, with defaults left out.
     *
     * Example: parameters(['status' => null]) keeps every filter but the statuses.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, string|int>
     */
    public function parameters(array $overrides = []): array
    {
        return collect([
            'q' => $this->search,
            'status' => implode(',', array_map(fn (ComponentStatusEnum $status): string => $status->name, $this->statuses)),
            'group' => $this->group,
            'tag' => $this->tag,
            'sort' => $this->sort === ComponentListSortEnum::severity ? null : $this->sort->value,
            'view' => $this->view === ComponentListViewEnum::list ? null : $this->view->value,
        ])
            ->merge($overrides)
            ->reject(fn (mixed $value): bool => $value === null || $value === '')
            ->all();
    }

    /**
     * Read a scalar query string value, ignoring arrays.
     */
    private static function string(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_scalar($value) ? (string) $value : '';
    }
}
