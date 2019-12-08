<?php

namespace Nevadskiy\Tokens\Tokens;

use DateInterval;
use DateTimeInterface;

interface GenerationLimit
{
    /**
     * Get the key for identifying attempts for throttling limiter on generation process.
     *
     * @return string
     */
    public function getGenerationLimiterKey(): string;

    /**
     * Get maximum token generation attempts amount for throttling limiter.
     *
     * @return int
     */
    public function getGenerationAttempts(): int;

    /**
     * Get the time interval limited generation attempts can be exhausted within.
     *
     * @return DateInterval|DateTimeInterface|int
     */
    public function getGenerationAttemptsInterval();
}
