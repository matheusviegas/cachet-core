<?php

namespace Cachet\Filament\Resources\Schedules\Pages;

use Cachet\Filament\Concerns\InteractsWithMeta;
use Cachet\Filament\Resources\Schedules\ScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditSchedule extends EditRecord
{
    use InteractsWithMeta;

    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ScheduleResource::recordUpdateAction()
                ->label(__('cachet::schedule.add_update.new_update_label'))
                ->icon(Heroicon::OutlinedPlus)
                ->after(fn () => $this->refreshCompletion()),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillMetaFormData($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['completed_at'] = $data['completed_at'] ?? null;

        return $this->extractMetaFormData($data);
    }

    protected function afterSave(): void
    {
        $this->persistMeta();
    }

    /**
     * Reload the completion time an update may set, so saving the form does not clear it.
     */
    private function refreshCompletion(): void
    {
        $this->getRecord()->refresh();
        $this->refreshFormData(['completed_at']);
    }
}
