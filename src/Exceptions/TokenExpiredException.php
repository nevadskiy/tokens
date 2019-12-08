<?php

namespace Nevadskiy\Tokens\Exceptions;

use Nevadskiy\Tokens\TokenEntity;

class TokenExpiredException extends TokenException
{
    /**
     * Static constructor.
     *
     * @param TokenEntity $token
     * @return TokenExpiredException
     */
    public static function fromToken(TokenEntity $token): self
    {
        $exception = new static('Token is already expired.');

        $exception->setToken($token);

        return $exception;
    }
}
