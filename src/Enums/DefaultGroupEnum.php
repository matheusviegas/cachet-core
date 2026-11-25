<?php

namespace Cachet\Enums;

use Cachet\Permissions;
use Filament\Support\Contracts\HasLabel;

/**
 * The groups that always exist. Their permissions follow the catalog, so
 * permissions added later reach them without a migration.
 */
enum DefaultGroupEnum: string implements HasLabel
{
    case administrators = 'administrators';
    case read_only = 'read-only';

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::administrators => Permissions::all(),
            self::read_only => Permissions::viewOnly(),
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::administrators => __('cachet::group.defaults.administrators'),
            self::read_only => __('cachet::group.defaults.read_only'),
        };
    }
}
