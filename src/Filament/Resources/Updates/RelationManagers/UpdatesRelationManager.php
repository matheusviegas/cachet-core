<?php

namespace Cachet\Filament\Resources\Updates\RelationManagers;

use Cachet\Actions\Incident\SyncIncidentStatus;
use Cachet\Enums\IncidentStatusEnum;
use Cachet\Filament\Resources\Incidents\Pages\EditIncident;
use Cachet\Filament\Resources\Users\UserResource;
use Cachet\Models\Incident;
use Cachet\Models\Schedule;
use Cachet\Permissions;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    /**
     * Incident and schedule updates have separate permissions.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $resource = $ownerRecord instanceof Schedule ? 'schedule-updates' : 'incident-updates';

        return Permissions::allows("{$resource}.".Permissions::VIEW);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                MarkdownEditor::make('message')
                    ->label(__('cachet::update.form.message_label'))
                    ->required()
                    ->minHeight('200px')
                    ->maxHeight('300px')
                    ->columnSpanFull(),
                Select::make('user_id')
                    ->label(__('cachet::update.form.user_label'))
                    ->hint(__('cachet::update.form.user_helper'))
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionAction(fn (Action $action): Action => $action->authorize(fn (): bool => UserResource::canCreate()))
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label(__('cachet::user.form.name_label'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('cachet::user.form.email_label'))
                            ->required()
                            ->email()
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label(__('cachet::user.form.password_label'))
                            ->required()
                            ->password()
                            ->confirmed()
                            ->minLength(8),
                    ]),
                ToggleButtons::make('status')
                    ->label(__('cachet::update.form.status_label'))
                    ->inline()
                    ->columnSpanFull()
                    ->options(IncidentStatusEnum::class)
                    ->required()
                    ->visible(fn (): bool => $this->getOwnerRecord() instanceof Incident),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('message')
            ->modelLabel(trans_choice('cachet::update.resource_label', 1))
            ->pluralModelLabel(trans_choice('cachet::update.resource_label', 2))
            ->columns([
                TextColumn::make('message')
                    ->label(__('cachet::update.list.headers.message'))
                    ->searchable()
                    ->limit(80)
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('cachet::update.list.headers.status'))
                    ->badge()
                    ->sortable()
                    ->visible(fn (): bool => $this->getOwnerRecord() instanceof Incident),
                TextColumn::make('user.name')
                    ->label(__('cachet::update.list.headers.user'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('cachet::update.list.headers.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('cachet::update.list.headers.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('cachet::update.list.headers.status'))
                    ->options(IncidentStatusEnum::class)
                    ->visible(fn (): bool => $this->getOwnerRecord() instanceof Incident),
            ])
            ->recordActions([
                EditAction::make()->after(fn () => $this->syncIncidentStatus()),
                DeleteAction::make()->after(fn () => $this->syncIncidentStatus()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->after(fn () => $this->syncIncidentStatus()),
                ]),
            ]);
    }

    /**
     * Keep the incident's status on its latest update, as the update actions do.
     */
    private function syncIncidentStatus(): void
    {
        $incident = $this->getOwnerRecord();

        if (! $incident instanceof Incident) {
            return;
        }

        app(SyncIncidentStatus::class)->handle($incident);

        // The edit page form holds the status it was loaded with; let it reload.
        $this->dispatch(EditIncident::STATUS_SYNCED_EVENT);
    }
}
