<?php

namespace Cachet\Enums;

use Filament\Support\Contracts\HasLabel;

enum ComponentListViewEnum: string implements HasLabel
{
    /** Paginated rows with names and statuses. */
    case list = 'list';

    /** One colored tile per component, grouped, for an at-a-glance overview. */
    case grid = 'grid';

    public function getLabel(): string
    {
        return __("cachet::component.explorer.view.{$this->value}");
    }
}
