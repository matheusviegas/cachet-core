<?php

namespace Cachet\Concerns;

/**
 * A user whose access comes from the permissions of their groups. Use the
 * HasGroups trait to implement it.
 */
interface CachetUser
{
    /**
     * @return list<string>
     */
    public function getPermissions(): array;

    public function hasPermission(string $permission): bool;

    /**
     * @param  list<string>  $permissions
     */
    public function hasAllPermissions(array $permissions): bool;
}
