<?php

namespace Cachet\Filament\Resources\ComponentGroups\Pages;

use Cachet\Filament\Concerns\InteractsWithMeta;
use Cachet\Filament\Resources\ComponentGroups\ComponentGroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * The read-only page for users who may view but not edit.
 */
class ViewComponentGroup extends ViewRecord
{
    use InteractsWithMeta;

    protected static string $resource = ComponentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillMetaFormData($data);
    }
}
