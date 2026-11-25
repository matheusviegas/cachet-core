<?php

use Illuminate\Support\Arr;
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

it('has a pt_BR translation for every English key', function () {
    $flatten = fn (array $lines): array => Arr::dot($lines);

    $missing = collect(glob(__DIR__.'/../../../resources/lang/en/*.php'))
        ->flatMap(function (string $path) use ($flatten): array {
            $file = basename($path, '.php');
            $portuguese = __DIR__."/../../../resources/lang/pt_BR/{$file}.php";
            $keys = array_keys(array_diff_key($flatten(require $path), file_exists($portuguese) ? $flatten(require $portuguese) : []));

            return array_map(fn (string $key): string => "{$file}.{$key}", $keys);
        })
        ->all();

    expect($missing)->toBe([]);
});

it('keeps the placeholders and plural forms of the English text in pt_BR', function () {
    $mismatches = collect(glob(__DIR__.'/../../../resources/lang/en/*.php'))
        ->flatMap(function (string $path): array {
            $file = basename($path, '.php');
            $english = Arr::dot(require $path);
            $portuguese = Arr::dot(require __DIR__."/../../../resources/lang/pt_BR/{$file}.php");
            $placeholders = function (string $line): array {
                preg_match_all('/:[a-z_]+/', $line, $matches);
                sort($matches[0]);

                return $matches[0];
            };

            return collect($english)
                ->filter(fn (mixed $line, string $key): bool => is_string($line) && is_string($portuguese[$key] ?? null))
                ->filter(fn (string $line, string $key): bool => $placeholders($line) !== $placeholders($portuguese[$key])
                    || substr_count($line, '|') !== substr_count($portuguese[$key], '|'))
                ->keys()
                ->map(fn (string $key): string => "{$file}.{$key}")
                ->all();
        })
        ->all();

    expect($mismatches)->toBe([]);
});
