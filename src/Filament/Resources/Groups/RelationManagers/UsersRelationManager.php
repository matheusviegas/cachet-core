<?php

namespace Cachet\Filament\Resources\Groups\RelationManagers;

use Cachet\Filament\Resources\Users\UserResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Lists a group's members. Membership is changed on the user form, so the
 * rules about who may grant which group live in one place.
 */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return UserResource::canViewAny();
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('cachet::group.users.title');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('cachet::user.list.headers.name'))
                    ->searchable(),
                TextColumn::make('email')
                    ->label(__('cachet::user.list.headers.email'))
                    ->searchable(),
            ]);
    }
}
