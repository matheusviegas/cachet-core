<?php

namespace Cachet\Models;

use Cachet\Enums\ComponentStatusEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A component's uptime for one day, recorded by cachet:record-uptime.
 *
 * @property int $id
 * @property int $component_id
 * @property Carbon $date
 * @property ComponentStatusEnum $status
 * @property int $severity
 * @property int $available_seconds
 * @property int $operational_seconds
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property Component $component
 */
class ComponentUptimeDay extends Model
{
    use MassPrunable;

    /** @var array<string, string> */
    protected $casts = [
        'date' => 'date',
        'status' => ComponentStatusEnum::class,
        'severity' => 'int',
        'available_seconds' => 'int',
        'operational_seconds' => 'int',
    ];

    /** @var list<string> */
    protected $fillable = [
        'component_id',
        'date',
        'status',
        'severity',
        'available_seconds',
        'operational_seconds',
    ];

    /**
     * Get the component the day belongs to.
     *
     * @return BelongsTo<Component, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Get the prunable model query.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('date', '<', now()->subDays(
            (int) config('cachet.uptime.retention_days', 365)
        )->toDateString());
    }
}
