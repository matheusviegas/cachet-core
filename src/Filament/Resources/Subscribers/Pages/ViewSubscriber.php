<?php

namespace Cachet\Filament\Resources\Subscribers\Pages;

use Cachet\Filament\Concerns\InteractsWithMeta;
use Cachet\Filament\Resources\Subscribers\SubscriberResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * The read-only page for users who may view but not edit.
 */
class ViewSubscriber extends ViewRecord
{
    use InteractsWithMeta;

    protected static string $resource = SubscriberResource::class;

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
