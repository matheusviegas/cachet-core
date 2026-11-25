<?php

namespace Cachet\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class EditProfile extends \Filament\Auth\Pages\EditProfile
{
    protected Width|string|null $maxContentWidth = Width::SixExtraLarge;

    public function getTitle(): string|Htmlable
    {
        return __('cachet::navigation.user.items.edit_profile');
    }

    public static function isSimple(): bool
    {
        return false;
    }

    public function getMultiFactorAuthenticationContentComponent(): ?Component
    {
        $component = parent::getMultiFactorAuthenticationContentComponent();

        if (! $component instanceof Section) {
            return $component;
        }

        return $component
            ->label(null)
            ->heading(__('filament-panels::auth/pages/edit-profile.multi_factor_authentication.label'));
    }

    /**
     * The dashboard language, or the browser's language when left on the system default.
     */
    protected function getPreferredLocaleFormComponent(): Component
    {
        return Select::make('preferred_locale')
            ->label(__('cachet::user.form.preferred_locale'))
            ->helperText(__('cachet::user.form.preferred_locale_helper'))
            ->options([
                '' => __('cachet::user.form.preferred_locale_system_default'),
                ...config('cachet.supported_locales', []),
            ])
            ->selectablePlaceholder(false)
            ->formatStateUsing(fn (?string $state): string => $state ?? '')
            ->dehydrateStateUsing(fn (?string $state): ?string => blank($state) ? null : $state);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->inlineLabel(false)
            ->components([
                Section::make(__('cachet::user.profile_information_title'))
                    ->columns(2)
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                    ]),

                Section::make(__('cachet::user.preferences_section_title'))
                    ->columns(2)
                    ->schema([
                        $this->getPreferredLocaleFormComponent(),
                    ]),

                Section::make(__('cachet::user.security_section_title'))
                    ->columns(2)
                    ->schema([
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),
            ]);
    }
}
