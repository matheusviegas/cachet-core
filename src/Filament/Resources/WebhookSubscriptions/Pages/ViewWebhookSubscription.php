<?php

namespace Cachet\Filament\Resources\WebhookSubscriptions\Pages;

use Cachet\Filament\Resources\WebhookSubscriptions\WebhookSubscriptionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * The read-only page for users who may view but not edit.
 */
class ViewWebhookSubscription extends ViewRecord
{
    protected static string $resource = WebhookSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
