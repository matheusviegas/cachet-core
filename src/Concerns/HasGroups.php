<?php

namespace Cachet\Concerns;

use Cachet\Enums\DefaultGroupEnum;
use Cachet\Models\Group;
use Cachet\Permissions;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Gives a user the permissions of every group they belong to.
 */
trait HasGroups
{
    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user', 'user_id', 'group_id')->withTimestamps();
    }

    /**
     * Get the union of the permissions granted by the user's groups.
     *
     * @return list<string>
     */
    public function getPermissions(): array
    {
        return $this->groups
            ->flatMap(fn (Group $group): array => $group->grantedPermissions())
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        return Permissions::isGranted($this->getPermissions(), $permission);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        $granted = $this->getPermissions();

        foreach ($permissions as $permission) {
            if (! Permissions::isGranted($granted, $permission)) {
                return false;
            }
        }

        return true;
    }

    public function isAdministrator(): bool
    {
        return $this->groups->contains(fn (Group $group): bool => $group->key === DefaultGroupEnum::administrators);
    }
}
