<?php

namespace Cachet\Filament\Resources\Groups\Pages;

use Cachet\Filament\Resources\Groups\GroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * The read-only page for users who may view but not edit.
 */
class ViewGroup extends ViewRecord
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
