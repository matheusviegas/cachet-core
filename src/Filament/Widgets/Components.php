<?php

namespace Cachet\Filament\Widgets;

use Cachet\Actions\Component\ChangeComponentStatus;
use Cachet\EffectiveComponentStatuses;
use Cachet\Enums\ComponentStatusEnum;
use Cachet\Enums\ComponentStatusSourceEnum;
use Cachet\Filament\Resources\Components\ComponentResource;
use Cachet\Filament\Widgets\Concerns\PollsFromAppSettings;
use Cachet\Models\Component;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Quick status changes for enabled components, most severe first.
 *
 * The table is paginated and searched on the server, so the widget stays the
 * same size whether there are ten components or thousands.
 */
class Components extends TableWidget
{
    use PollsFromAppSettings;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return ComponentResource::canViewAny();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => app(EffectiveComponentStatuses::class)
                ->orderBySeverity(Component::query()->enabled()->with('group'))
                ->orderBy('components.name'))
            ->heading(trans_choice('cachet::component.resource_label', 2))
            ->poll($this->getPollingInterval())
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('name')
                    ->label(__('cachet::component.list.headers.name'))
                    ->searchable(),
                TextColumn::make('group.name')
                    ->label(__('cachet::component.list.headers.group'))
                    ->placeholder(__('cachet::component.list.ungrouped')),
                TextColumn::make('effective_status')
                    ->label(__('cachet::dashboard.components.effective_status'))
                    ->state(fn (Component $record): ComponentStatusEnum => app(EffectiveComponentStatuses::class)->of($record))
                    ->badge(),
                SelectColumn::make('status')
                    ->authorize('update')
                    ->label(__('cachet::dashboard.components.baseline_status'))
                    ->options(ComponentStatusEnum::class)
                    ->selectablePlaceholder(false)
                    ->updateStateUsing(function (Component $record, mixed $state): ComponentStatusEnum {
                        $status = $state instanceof ComponentStatusEnum ? $state : ComponentStatusEnum::from((int) $state);

                        app(ChangeComponentStatus::class)->handle($record, $status, ComponentStatusSourceEnum::Manual, auth()->user());

                        Notification::make()
                            ->title(__('cachet::dashboard.components.status_updated', ['component' => $record->name, 'status' => $status->getLabel()]))
                            ->success()
                            ->send();

                        return $status;
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('cachet::dashboard.components.effective_status'))
                    ->options(ComponentStatusEnum::class)
                    ->multiple()
                    ->query(fn (Builder $query, array $data): Builder => blank($data['values'] ?? null)
                        ? $query
                        : app(EffectiveComponentStatuses::class)->whereStatusIn(
                            $query,
                            array_map(fn (mixed $value): ComponentStatusEnum => ComponentStatusEnum::from((int) $value), $data['values']),
                        )),
                SelectFilter::make('group')
                    ->label(__('cachet::component.list.headers.group'))
                    ->relationship('group', 'name')
                    ->searchable(),
            ]);
    }
}
