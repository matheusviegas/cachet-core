<?php

namespace Cachet\Filament\Resources\Users;

use Cachet\Cachet;
use Cachet\Concerns\CachetUser;
use Cachet\Enums\DefaultGroupEnum;
use Cachet\Filament\Resources\Users\Pages\CreateUser;
use Cachet\Filament\Resources\Users\Pages\EditUser;
use Cachet\Filament\Resources\Users\Pages\ListUsers;
use Cachet\Filament\Resources\Users\Pages\ViewUser;
use Cachet\Models\Group;
use Cachet\Models\User;
use Cachet\Permissions;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function getViewAnyAuthorizationResponse(): Response
    {
        return self::permissionResponse(Permissions::VIEW);
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return self::permissionResponse(Permissions::VIEW);
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return self::permissionResponse(Permissions::MANAGE);
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        if (Cachet::demoMode()) {
            return Response::deny();
        }

        if (auth()->user()->is($record)) {
            return Response::allow();
        }

        if (! self::outranks($record)) {
            return Response::deny();
        }

        return self::permissionResponse(Permissions::MANAGE);
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        if (! $record instanceof User || auth()->user()->is($record) || ! self::outranks($record)) {
            return Response::deny();
        }

        if ($record->isAdministrator() && Group::findDefault(DefaultGroupEnum::administrators)?->users()->count() <= 1) {
            return Response::deny();
        }

        return self::permissionResponse(Permissions::DELETE);
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return self::permissionResponse(Permissions::DELETE);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns()->schema([
                    TextInput::make('name')
                        ->label(__('cachet::user.form.name_label'))
                        ->required()
                        ->maxLength(255)
                        ->autocomplete(false),

                    TextInput::make('email')
                        ->label(__('cachet::user.form.email_label'))
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->autocomplete(false)
                        ->unique('users', 'email'),

                    TextInput::make('password')
                        ->label(__('cachet::user.form.password_label'))
                        ->password()
                        ->required(fn (string $context): bool => $context === 'create')
                        ->maxLength(255)
                        ->autocomplete(false)
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                        ->dehydrated(fn ($state) => filled($state)),

                    TextInput::make('password_confirmation')
                        ->password()
                        ->required(fn (string $context): bool => $context === 'create')
                        ->maxLength(255)
                        ->same('password')
                        ->label(__('cachet::user.form.password_confirmation_label')),

                    Select::make('preferred_locale')
                        ->selectablePlaceholder(false)
                        ->options([
                            '' => __('cachet::user.form.preferred_locale_system_default'),
                            ...config('cachet.supported_locales'),
                        ])
                        ->label(__('cachet::user.form.preferred_locale')),

                    Select::make('groups')
                        ->label(__('cachet::user.form.groups_label'))
                        ->helperText(__('cachet::user.form.groups_helper'))
                        ->relationship('groups', 'name', fn (Builder $query): Builder => $query->whereKey(self::assignableGroupIds()))
                        ->getOptionLabelFromRecordUsing(fn (Group $group): string => $group->name)
                        ->multiple()
                        ->preload()
                        ->default(fn (): array => array_filter([Group::findDefault(DefaultGroupEnum::read_only)?->id]))
                        ->disabled(fn (?User $record) => $record?->is(auth()->user())),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('cachet::user.list.headers.name'))
                    ->searchable(),

                TextColumn::make('email')
                    ->label(__('cachet::user.list.headers.email'))
                    ->searchable(),

                TextColumn::make('groups.name')
                    ->label(__('cachet::user.list.headers.groups'))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('email_verified_at')
                    ->label(__('cachet::user.list.headers.email_verified_at'))
                    ->dateTime(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make()
                    ->hidden(fn (Model $record): bool => static::canEdit($record)),
                EditAction::make(),
                Action::make('verify-email')
                    ->authorize(fn (User $record): bool => static::canEdit($record))
                    ->label(__('cachet::user.list.actions.verify_email'))
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->disabled(fn (User $record): bool => $record->hasVerifiedEmail())
                    ->action(function (Action $action, User $record): void {
                        $record->sendEmailVerificationNotification();

                        $action->success();
                    })
                    ->successNotificationTitle(fn (User $record): string => __('cachet::user.list.actions.verify_email_success', ['email' => $record->email])),
                Action::make('reset-two-factor')
                    ->authorize(fn (User $record): bool => static::canEdit($record))
                    ->label(__('cachet::user.list.actions.reset_two_factor'))
                    ->icon(Heroicon::OutlinedShieldExclamation)
                    ->requiresConfirmation()
                    ->modalDescription(__('cachet::user.list.actions.reset_two_factor_confirmation'))
                    ->visible(fn (User $record): bool => filled($record->getAppAuthenticationSecret()))
                    ->action(function (Action $action, User $record): void {
                        $record->saveAppAuthenticationSecret(null);
                        $record->saveAppAuthenticationRecoveryCodes(null);

                        $action->success();
                    })
                    ->successNotificationTitle(__('cachet::user.list.actions.reset_two_factor_success')),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
            'view' => ViewUser::route('/{record}'),
        ];
    }

    public static function getLabel(): ?string
    {
        return trans_choice('cachet::user.resource_label', 1);
    }

    public static function getPluralLabel(): ?string
    {
        return trans_choice('cachet::user.resource_label', 2);
    }

    public static function getModel(): string
    {
        return Cachet::userModel()::class;
    }

    private static function permissionResponse(string $ability): Response
    {
        return Permissions::allows("users.{$ability}") ? Response::allow() : Response::deny();
    }

    /**
     * Nobody may change a user who holds permissions they lack, or they could
     * strip an administrator or act through a more privileged account.
     */
    private static function outranks(Model $record): bool
    {
        $user = Cachet::user();

        return $record instanceof CachetUser
            && $user instanceof CachetUser
            && $user->hasAllPermissions($record->getPermissions());
    }

    /**
     * Get the groups the signed-in user may hand out: those granting nothing beyond their own permissions.
     *
     * @return list<int>
     */
    private static function assignableGroupIds(): array
    {
        $user = Cachet::user();

        if (! $user instanceof CachetUser) {
            return [];
        }

        return Group::query()->get()
            ->filter(fn (Group $group): bool => $user->hasAllPermissions($group->grantedPermissions()))
            ->modelKeys();
    }
}
