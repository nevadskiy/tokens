<?php

namespace Nevadskiy\Tokens\Tokens;

use DateInterval;
use DateTimeInterface;

interface Token
{
    /**
     * Get the token name.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Generate a token string.
     *
     * @return string
     */
    public function generate(): string;

    /**
     * Get the token expiration date.
     *
     * @return DateInterval|DateTimeInterface|int
     */
    public function getExpirationDate();

    /**
     * Get the token generation strategy name.
     * Can be one of ['remove', 'keep', 'reuse'].
     *
     * @return string
     */
    public function getGenerationStrategy(): string;
}
