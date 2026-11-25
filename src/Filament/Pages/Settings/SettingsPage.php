<?php

namespace Cachet\Filament\Pages\Settings;

use Cachet\Permissions;

abstract class SettingsPage extends \Filament\Pages\SettingsPage
{
    public static function canAccess(): bool
    {
        return Permissions::allows(Permissions::SETTINGS);
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }
}
