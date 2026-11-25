<?php

namespace Cachet\Filament\Resources\Metrics\Pages;

use Cachet\Filament\Resources\Metrics\MetricResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * The read-only page for users who may view but not edit.
 */
class ViewMetric extends ViewRecord
{
    protected static string $resource = MetricResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
