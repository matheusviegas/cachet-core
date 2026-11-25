<?php

namespace Cachet\Policies;

use Cachet\Models\Schedule;
use Cachet\Models\Update;
use Illuminate\Database\Eloquent\Model;

/**
 * Updates belong to incidents or schedules, each with its own permissions.
 */
class UpdatePolicy extends ResourcePolicy
{
    private const INCIDENT_UPDATES = 'incident-updates';

    private const SCHEDULE_UPDATES = 'schedule-updates';

    public function __construct()
    {
        parent::__construct(self::INCIDENT_UPDATES);
    }

    /**
     * Without a record, either kind of update permission is enough.
     *
     * @return list<string>
     */
    protected function resourcesFor(?Model $record): array
    {
        if (! $record instanceof Update) {
            return [self::INCIDENT_UPDATES, self::SCHEDULE_UPDATES];
        }

        if ($record->updateable_type === (new Schedule)->getMorphClass()) {
            return [self::SCHEDULE_UPDATES];
        }

        return [self::INCIDENT_UPDATES];
    }
}
