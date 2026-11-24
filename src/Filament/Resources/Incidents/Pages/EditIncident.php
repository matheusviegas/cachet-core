<?php

namespace Cachet\Filament\Resources\Incidents\Pages;

use Cachet\Filament\Concerns\InteractsWithMeta;
use Cachet\Filament\Resources\Incidents\IncidentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;

class EditIncident extends EditRecord
{
    use InteractsWithMeta;

    /**
     * Dispatched when updates change the incident status outside the edit form.
     */
    public const STATUS_SYNCED_EVENT = 'incident-status-synced';

    protected static string $resource = IncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            IncidentResource::recordUpdateAction()
                ->label(__('cachet::incident.record_update.new_update_label'))
                ->icon(Heroicon::OutlinedPlus)
                ->after(fn () => $this->refreshIncidentStatus()),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillMetaFormData($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractMetaFormData($data);
    }

    protected function afterSave(): void
    {
        $this->persistMeta();
    }

    /**
     * Reload the status after updates change it, so saving the form does not write the stale one back.
     */
    #[On(self::STATUS_SYNCED_EVENT)]
    public function refreshIncidentStatus(): void
    {
        $this->getRecord()->refresh();
        $this->refreshFormData(['status']);
    }
}
