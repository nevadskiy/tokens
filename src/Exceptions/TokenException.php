<?php

namespace Nevadskiy\Tokens\Exceptions;

use Exception;
use Nevadskiy\Tokens\TokenEntity;

class TokenException extends Exception
{
    /**
     * @var TokenEntity
     */
    protected $token;

    /**
     * Set the token to the exception.
     *
     * @param TokenEntity $token
     */
    public function setToken(TokenEntity $token): void
    {
        $this->token = $token;
    }

    /**
     * Get the token from the exception.
     *
     * @return TokenEntity
     */
    public function getToken(): TokenEntity
    {
        return $this->token;
    }
}
