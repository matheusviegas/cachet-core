<?php

namespace Cachet\Policies;

use Cachet\Concerns\CachetUser;
use Cachet\Models\Group;
use Illuminate\Database\Eloquent\Model;

/**
 * Default groups are fixed, and nobody may change a group that grants more
 * than they hold, so managing groups cannot be used to gain permissions.
 */
class GroupPolicy extends ResourcePolicy
{
    public function __construct()
    {
        parent::__construct('groups');
    }

    public function update(CachetUser $user, Model $record): bool
    {
        return parent::update($user, $record) && $this->isChangeable($user, $record);
    }

    public function delete(CachetUser $user, Model $record): bool
    {
        return parent::delete($user, $record) && $this->isChangeable($user, $record);
    }

    private function isChangeable(CachetUser $user, Model $record): bool
    {
        if (! $record instanceof Group || $record->isDefault()) {
            return false;
        }

        return $user->hasAllPermissions($record->grantedPermissions());
    }
}
