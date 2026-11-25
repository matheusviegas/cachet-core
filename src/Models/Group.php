<?php

namespace Cachet\Models;

use Cachet\Database\Factories\GroupFactory;
use Cachet\Enums\DefaultGroupEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A set of permissions granted to its members.
 *
 * @property int $id
 * @property ?DefaultGroupEnum $key
 * @property string $name
 * @property ?list<string> $permissions
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /** @var array<string, string> */
    protected $casts = [
        'key' => DefaultGroupEnum::class,
        'permissions' => 'array',
    ];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'permissions',
    ];

    /**
     * Get the group's members.
     *
     * @return BelongsToMany<Model, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('cachet.user_model'), 'group_user', 'group_id', 'user_id')->withTimestamps();
    }

    /**
     * Get a default group.
     */
    public static function findDefault(DefaultGroupEnum $key): ?self
    {
        return static::query()->where('key', $key->value)->first();
    }

    /**
     * Determine whether the group always exists and follows the catalog.
     */
    public function isDefault(): bool
    {
        return $this->key !== null;
    }

    /**
     * Get the permissions the group grants. Default groups follow the catalog.
     *
     * @return list<string>
     */
    public function grantedPermissions(): array
    {
        return $this->key?->permissions() ?? $this->permissions ?? [];
    }

    /**
     * Default groups show their name in the user's language.
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (?string $value): ?string => $this->key?->getLabel() ?? $value);
    }

    protected static function newFactory(): GroupFactory
    {
        return GroupFactory::new();
    }
}
