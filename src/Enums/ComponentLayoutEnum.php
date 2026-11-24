<?php

namespace Cachet\Enums;

use Filament\Support\Contracts\HasLabel;

enum ComponentLayoutEnum: string implements HasLabel
{
    /** Every component, nested in collapsible groups. Suits a few dozen components. */
    case groups = 'groups';

    /** A searchable, filterable and paginated list with a status summary and a grid view. */
    case list = 'list';

    public function getLabel(): string
    {
        return __("cachet::settings.manage_cachet.component_layout.options.{$this->value}");
    }
}
