<?php

use Illuminate\Support\Facades\Lang;

it('translates the theme, update and component status labels into pt_BR', function (string $key) {
    expect(Lang::hasForLocale("cachet::{$key}", 'pt_BR'))->toBeTrue()
        ->and(__("cachet::{$key}", locale: 'pt_BR'))->not->toBe(__("cachet::{$key}", locale: 'en'));
})->with([
    'cachet.and',
    'cachet.theme.label',
    'cachet.theme.auto',
    'cachet.theme.light',
    'cachet.theme.dark',
    'incident.record_update.form.components_label',
    'incident.record_update.form.components_helper',
    'incident.record_update.form.components_add_action_label',
    'metric.form.suffix_placeholder',
    'settings.manage_theme.theme_mode.heading',
    'settings.manage_theme.theme_mode.description',
    'settings.manage_theme.theme_mode.label',
    'settings.manage_theme.theme_mode.helper',
    'settings.manage_theme.theme_mode.modes.auto',
    'settings.manage_theme.theme_mode.modes.light',
    'settings.manage_theme.theme_mode.modes.dark',
    'update.resource_label',
    'update.list.headers.message',
    'update.list.headers.user',
    'update.list.headers.created_at',
    'update.list.headers.updated_at',
    'update.form.message_label',
    'update.form.user_label',
    'update.form.user_helper',
]);

it('keeps untranslatable update labels in pt_BR', function (string $key) {
    expect(Lang::hasForLocale("cachet::{$key}", 'pt_BR'))->toBeTrue();
})->with([
    'update.list.headers.status',
    'update.form.status_label',
]);
