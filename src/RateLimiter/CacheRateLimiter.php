<?php

namespace Nevadskiy\Tokens\RateLimiter;

use Carbon\Carbon;
use DateInterval;
use Illuminate\Cache\RateLimiter as BaseRateLimiter;
use Nevadskiy\Tokens\Exceptions\LockoutException;

class CacheRateLimiter implements RateLimiter
{
    /**
     * @var BaseRateLimiter
     */
    private $limiter;

    /**
     * CacheRateLimiter constructor.
     *
     * @param BaseRateLimiter $limiter
     */
    public function __construct(BaseRateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle a callback with rate limiting applied.
     *
     * @param string $key
     * @param int $attempts
     * @param DateInterval $timeout
     * @param callable $callback
     * @return mixed
     * @throws LockoutException
     */
    public function limit(string $key, int $attempts, DateInterval $timeout, callable $callback)
    {
        $this->attempt($key, $attempts, $timeout);

        $result = $callback();

        $this->clear($key);

        return $result;
    }

    /**
     * Guard against too many request attempts.
     *
     * @param string $key
     * @param int $attempts
     * @throws LockoutException
     */
    public function guardTooManyAttempts(string $key, int $attempts): void
    {
        if ($this->isLocked($key, $attempts)) {
            $this->throwLockoutException($key);
        }
    }

    /**
     * Determine if the client reached the maximum available request attempts by the key.
     *
     * @param string $key
     * @param int $attempts
     * @return bool
     */
    public function isLocked(string $key, int $attempts): bool
    {
        return $this->limiter->tooManyAttempts($key, $attempts);
    }

    /**
     * Clear the attempts by the key.
     *
     * @param string $key
     * @return void
     */
    public function clear(string $key): void
    {
        $this->limiter->clear($key);
    }

    /**
     * Increment the attempts for by the key with timeout.
     *
     * @param string $key
     * @param int $attempts
     * @param DateInterval $timeout
     * @return void
     * @throws LockoutException
     */
    public function attempt(string $key, int $attempts, DateInterval $timeout): void
    {
        $this->guardTooManyAttempts($key, $attempts);
        $this->limiter->hit($key, $timeout);
    }

    /**
     * Throw an exception when a lockout occurs.
     *
     * @param string $key
     * @throws LockoutException
     */
    protected function throwLockoutException(string $key): void
    {
        throw LockoutException::withTimeout(
            Carbon::now()->addSeconds($this->limiter->availableIn($key)),
            'Too many attempts. Please wait before retrying.'
        );
    }
}
