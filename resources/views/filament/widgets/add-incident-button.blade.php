@if (\Cachet\Filament\Resources\Incidents\IncidentResource::canCreate())
    <x-filament::link href="{{ \Cachet\Filament\Resources\Incidents\IncidentResource::getUrl('create') }}">{{ __('cachet::incident.new_button') }}</x-filament::link>
@endif
