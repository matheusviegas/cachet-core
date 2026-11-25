<?php

namespace Cachet\Filament\Resources\Groups;

use Cachet\Filament\Resources\Groups\Pages\CreateGroup;
use Cachet\Filament\Resources\Groups\Pages\EditGroup;
use Cachet\Filament\Resources\Groups\Pages\ListGroups;
use Cachet\Filament\Resources\Groups\Pages\ViewGroup;
use Cachet\Filament\Resources\Groups\RelationManagers\UsersRelationManager;
use Cachet\Models\Group;
use Cachet\Permissions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    /**
     * Unsorted items come first, so this places groups right after users.
     */
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->schema([
                    TextInput::make('name')
                        ->label(__('cachet::group.form.name_label'))
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->autocomplete(false)
                        ->disabled(fn (?Group $record): bool => (bool) $record?->isDefault()),

                    // Laid out in rows, so each resource's view/manage/delete line up.
                    CheckboxList::make('permissions')
                        ->label(__('cachet::group.form.permissions_label'))
                        ->helperText(fn (?Group $record): string => $record?->isDefault()
                            ? __('cachet::group.form.default_helper')
                            : __('cachet::group.form.permissions_helper'))
                        ->options(fn (string $operation, ?Group $record): array => $operation === 'view' || $record?->isDefault()
                            ? Permissions::labels()
                            : self::grantablePermissions())
                        ->formatStateUsing(fn (?Group $record, ?array $state): array => $record?->isDefault()
                            ? $record->grantedPermissions()
                            : ($state ?? []))
                        ->disabled(fn (?Group $record): bool => (bool) $record?->isDefault())
                        ->nestedRecursiveRules([
                            fn (CheckboxList $component): In => Rule::in(array_keys($component->getOptions())),
                        ])
                        ->bulkToggleable()
                        ->columns(3)
                        ->gridDirection('row')
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('cachet::group.list.headers.name'))
                    ->badge(fn (Group $record): bool => $record->isDefault())
                    ->searchable(),
                TextColumn::make('permissions')
                    ->label(__('cachet::group.list.headers.permissions'))
                    ->state(fn (Group $record): int => count($record->grantedPermissions())),
                TextColumn::make('users_count')
                    ->label(__('cachet::group.list.headers.users'))
                    ->counts('users')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->hidden(fn (Model $record): bool => static::canEdit($record)),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGroups::route('/'),
            'create' => CreateGroup::route('/create'),
            'edit' => EditGroup::route('/{record}/edit'),
            'view' => ViewGroup::route('/{record}'),
        ];
    }

    public static function getLabel(): ?string
    {
        return trans_choice('cachet::group.resource_label', 1);
    }

    public static function getPluralLabel(): ?string
    {
        return trans_choice('cachet::group.resource_label', 2);
    }

    /**
     * Only offer the permissions the user holds, so nobody can create a
     * group more powerful than themselves.
     *
     * @return array<string, string>
     */
    private static function grantablePermissions(): array
    {
        return array_filter(
            Permissions::labels(),
            fn (string $permission): bool => Permissions::allows($permission),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
