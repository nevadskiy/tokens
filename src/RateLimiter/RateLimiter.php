<?php

namespace Nevadskiy\Tokens\RateLimiter;

use DateInterval;
use Nevadskiy\Tokens\Exceptions\LockoutException;

interface RateLimiter
{
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
    public function limit(string $key, int $attempts, DateInterval $timeout, callable $callback);

    /**
     * Increment the attempts for by the key with timeout.
     *
     * @param string $key
     * @param int $attempts
     * @param DateInterval $timeout
     * @return void
     * @throws LockoutException
     */
    public function attempt(string $key, int $attempts, DateInterval $timeout): void;
}
