<?php

declare(strict_types=1);

namespace Cachet;

use Cachet\Concerns\CachetUser;

/**
 * The catalog of permissions groups can grant.
 *
 * A permission is "<resource>.<ability>", e.g. "incidents.manage". Granting
 * "manage" or "delete" on a resource implies "view", so a group cannot let
 * someone edit a screen they cannot open.
 */
final class Permissions
{
    public const VIEW = 'view';

    public const MANAGE = 'manage';

    public const DELETE = 'delete';

    public const SETTINGS = 'settings.manage';

    public const API_KEYS = 'api-keys.manage';

    /**
     * Resources that only exist in the dashboard, so API tokens never carry them.
     *
     * @var array<string, list<string>>
     */
    private const DASHBOARD_RESOURCES = [
        'webhooks' => [self::VIEW, self::MANAGE, self::DELETE],
        'users' => [self::VIEW, self::MANAGE, self::DELETE],
        'groups' => [self::VIEW, self::MANAGE, self::DELETE],
        'api-keys' => [self::MANAGE],
        'settings' => [self::MANAGE],
    ];

    /**
     * Get the abilities of every resource, API resources first.
     *
     * @return array<string, list<string>>
     */
    public static function resources(): array
    {
        $resources = [];

        foreach (Cachet::getResourceApiAbilities() as $resource => $abilities) {
            $resources[$resource] = [self::VIEW, ...$abilities];
        }

        return [...$resources, ...self::DASHBOARD_RESOURCES];
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $permissions = [];

        foreach (self::resources() as $resource => $abilities) {
            foreach ($abilities as $ability) {
                $permissions[] = "{$resource}.{$ability}";
            }
        }

        return $permissions;
    }

    /**
     * @return list<string>
     */
    public static function viewOnly(): array
    {
        return array_values(array_filter(
            self::all(),
            fn (string $permission): bool => str_ends_with($permission, '.'.self::VIEW),
        ));
    }

    /**
     * Get the translated label of every permission, keyed by permission.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::all() as $permission) {
            $labels[$permission] = self::label($permission);
        }

        return $labels;
    }

    /**
     * Translate a permission, e.g. "incidents.manage" to "Manage incidents".
     */
    public static function label(string $permission): string
    {
        [$resource, $ability] = explode('.', $permission, 2);

        return __("cachet::permission.abilities.{$ability}", [
            'resource' => __("cachet::permission.resources.{$resource}"),
        ]);
    }

    /**
     * Determine whether the granted permissions include the given one.
     *
     * @param  list<string>  $granted
     */
    public static function isGranted(array $granted, string $permission): bool
    {
        if (in_array($permission, $granted, true)) {
            return true;
        }

        [$resource, $ability] = array_pad(explode('.', $permission, 2), 2, '');

        if ($ability !== self::VIEW) {
            return false;
        }

        return in_array("{$resource}.".self::MANAGE, $granted, true)
            || in_array("{$resource}.".self::DELETE, $granted, true);
    }

    /**
     * Determine whether the signed-in user holds the given permission.
     */
    public static function allows(string $permission): bool
    {
        $user = Cachet::user();

        return $user instanceof CachetUser && $user->hasPermission($permission);
    }
}
