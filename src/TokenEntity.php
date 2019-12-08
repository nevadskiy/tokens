<?php

namespace Nevadskiy\Tokens;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int tokenable_id
 * @property string tokenable_type
 * @property string token
 * @property string name
 * @property Model tokenable
 * @property Carbon expired_at
 * @property Carbon used_at
 * @property Carbon created_at
 * @property Carbon updated_at
 */
class TokenEntity extends Model
{
    use SoftDeletes;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'used_at',
        'expired_at',
    ];

    /**
     * Get the last generated token entity.
     *
     * @return TokenEntity
     */
    public static function last(): self
    {
        return self::latest('id')->firstOrFail();
    }

    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setTable(config('tokens.table'));
    }

    /**
     * Get tokenable model which the token is related to.
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Fill tokenable attributes according to the given model.
     *
     * @param Model $tokenable
     */
    public function fillTokenable(Model $tokenable): void
    {
        $this->fill($this->getTokenableAttributes($tokenable));
    }

    /**
     * Scope a query to only include tokens which belong to tokenable entity.
     *
     * @param Builder $query
     * @param Model $tokenable
     * @return Builder
     */
    public function scopeForTokenable(Builder $query, Model $tokenable): Builder
    {
        return $query->where($this->getTokenableAttributes($tokenable));
    }

    /**
     * Scope a query to only include tokens which are already expired, used or removed.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeDead(Builder $query): Builder
    {
        return $query->withoutGlobalScopes()
            ->whereNotNull('used_at')
            ->orWhere('expired_at', '<', $this->freshTimestamp())
            ->orWhereNotNull('deleted_at');
    }

    /**
     * Get tokenable attributes of the given model.
     *
     * @param Model $tokenable
     * @return array
     */
    protected function getTokenableAttributes(Model $tokenable): array
    {
        return [
            'tokenable_id' => $tokenable->getKey(),
            'tokenable_type' => get_class($tokenable),
        ];
    }

    /**
     * Scope a query to only include active tokens.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expired_at', '>', $this->freshTimestamp());
    }

    /**
     * Continue the token expire date.
     *
     * @param Carbon $date
     */
    public function continueTo(Carbon $date): void
    {
        $this->update(['expired_at' => $date]);
    }

    /**
     * Mark the token as used.
     */
    public function markAsUsed(): void
    {
        $this->update(['used_at' => $this->freshTimestamp()]);
    }

    /**
     * Determine if the token is expired already.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expired_at->isPast();
    }

    /**
     * Determine if the token is already used.
     *
     * @return bool
     */
    public function isUsed(): bool
    {
        return (bool) $this->used_at;
    }

    /**
     * Convert the token entity to the string.
     *
     * @return string
     */
    public function toString(): string
    {
        return $this->token;
    }

    /**
     * Convert the token entity to the string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toString();
    }
}
