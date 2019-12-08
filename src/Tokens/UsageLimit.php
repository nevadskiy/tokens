<?php

namespace Nevadskiy\Tokens\Tokens;

use DateInterval;
use DateTimeInterface;

interface UsageLimit
{
    /**
     * Get the key for identifying attempts for throttling limiter on usage process.
     *
     * @return string
     */
    public function getUsageLimiterKey(): string;

    /**
     * Get maximum token usage attempts amount for throttling limiter.
     *
     * @return int
     */
    public function getUsageAttempts(): int;

    /**
     * Get the time interval limited usage attempts can be exhausted within.
     *
     * @return DateInterval|DateTimeInterface|int
     */
    public function getUsageAttemptsInterval();
}
