<?php

namespace Nevadskiy\Tokens\Tests\Support\Factory;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Nevadskiy\Tokens\TokenEntity;

class TokenFactory
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var Model
     */
    private $tokenable;

    /**
     * @var Carbon
     */
    private $expireDate;

    /**
     * @var Carbon
     */
    private $usageDate;

    /**
     * Create the token with provided parameters.
     *
     * @param string|null $token
     * @return TokenEntity
     */
    public function create(string $token = null): TokenEntity
    {
        $token = factory(TokenEntity::class)->make([
            'name' => $this->name ?: 'verification',
            'used_at' => null,
            'expired_at' => now()->addMonth(),
            'token' => $token ?: Str::random(10),
        ]);

        if ($this->tokenable) {
            $token->fillTokenable($this->tokenable);
        }

        if ($this->expireDate) {
            $token->expired_at = $this->expireDate;
        }

        if ($this->usageDate) {
            $token->used_at = $this->usageDate;
        }

        $token->save();

        return $token;
    }

    /**
     * Set a tokenable model which token will be created for.
     *
     * @param Model $tokenable
     * @return $this
     */
    public function for(Model $tokenable): self
    {
        $this->tokenable = $tokenable;

        return $this;
    }

    /**
     * Set a token name.
     *
     * @param string $name
     * @return $this
     */
    public function withName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set expire date.
     *
     * @param Carbon|null $expireDate
     * @return $this
     */
    public function expired(Carbon $expireDate = null): self
    {
        $this->expireDate = $expireDate ?: Carbon::now()->subMinute();

        return $this;
    }

    /**
     * Set usage date.
     *
     * @param Carbon|null $usageDate
     * @return $this
     */
    public function used(Carbon $usageDate = null): self
    {
        $this->usageDate = $usageDate ?: Carbon::now();

        return $this;
    }
}
