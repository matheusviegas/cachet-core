<?php

namespace Cachet\Filament\Resources\IncidentTemplates\Pages;

use Cachet\Filament\Resources\IncidentTemplates\IncidentTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * The read-only page for users who may view but not edit.
 */
class ViewIncidentTemplate extends ViewRecord
{
    protected static string $resource = IncidentTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
