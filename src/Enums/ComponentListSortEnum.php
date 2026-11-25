<?php

namespace Cachet\Enums;

use Filament\Support\Contracts\HasLabel;

enum ComponentListSortEnum: string implements HasLabel
{
    /** Most severe effective status first. */
    case severity = 'severity';

    case name = 'name';

    /** By component group, in the dashboard order. */
    case group = 'group';

    public function getLabel(): string
    {
        return __("cachet::component.explorer.sort.{$this->value}");
    }
}
