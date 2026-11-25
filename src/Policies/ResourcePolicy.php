<?php

namespace Cachet\Policies;

use Cachet\Concerns\CachetUser;
use Cachet\Permissions;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps the policy methods Filament and Laravel call to one resource's
 * permissions: viewing needs "view", writing "manage", removing "delete".
 */
class ResourcePolicy
{
    public function __construct(private readonly string $resource) {}

    public function viewAny(CachetUser $user): bool
    {
        return $this->allows($user, Permissions::VIEW);
    }

    public function view(CachetUser $user, Model $record): bool
    {
        return $this->allows($user, Permissions::VIEW, $record);
    }

    public function create(CachetUser $user): bool
    {
        return $this->allows($user, Permissions::MANAGE);
    }

    public function update(CachetUser $user, Model $record): bool
    {
        return $this->allows($user, Permissions::MANAGE, $record);
    }

    public function replicate(CachetUser $user, Model $record): bool
    {
        return $this->allows($user, Permissions::MANAGE, $record);
    }

    public function reorder(CachetUser $user): bool
    {
        return $this->allows($user, Permissions::MANAGE);
    }

    public function delete(CachetUser $user, Model $record): bool
    {
        return $this->allows($user, Permissions::DELETE, $record);
    }

    public function deleteAny(CachetUser $user): bool
    {
        return $this->allows($user, Permissions::DELETE);
    }

    public function forceDelete(CachetUser $user, Model $record): bool
    {
        return $this->allows($user, Permissions::DELETE, $record);
    }

    public function forceDeleteAny(CachetUser $user): bool
    {
        return $this->allows($user, Permissions::DELETE);
    }

    public function restore(CachetUser $user, Model $record): bool
    {
        return $this->allows($user, Permissions::DELETE, $record);
    }

    public function restoreAny(CachetUser $user): bool
    {
        return $this->allows($user, Permissions::DELETE);
    }

    /**
     * Determine whether the user holds the ability on the record's resource.
     */
    protected function allows(CachetUser $user, string $ability, ?Model $record = null): bool
    {
        foreach ($this->resourcesFor($record) as $resource) {
            if ($user->hasPermission("{$resource}.{$ability}")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the resources whose permissions cover the record.
     *
     * @return list<string>
     */
    protected function resourcesFor(?Model $record): array
    {
        return [$this->resource];
    }
}
