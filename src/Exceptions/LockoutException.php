<?php

namespace Nevadskiy\Tokens\Exceptions;

use Carbon\Carbon;
use DateTimeInterface;

class LockoutException extends TokenException
{
    /**
     * @var DateTimeInterface
     */
    private $timeout;

    /**
     * Create a new exception instance.
     *
     * @param string $message
     * @param Carbon $timeout
     */
    public function __construct(string $message = '', Carbon $timeout = null)
    {
        parent::__construct($message);
        $this->timeout = $timeout;
    }

    /**
     * Static constructor.
     *
     * @param Carbon $timeout
     * @param string $message
     * @return LockoutException
     */
    public static function withTimeout(Carbon $timeout = null, string $message = ''): self
    {
        return new static($message, $timeout);
    }

    /**
     * Get the unlock time.
     *
     * @return Carbon
     */
    public function getUnlockTime(): Carbon
    {
        return $this->timeout;
    }
}
