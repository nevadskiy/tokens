<?php

namespace Nevadskiy\Tokens\Exceptions;

use Nevadskiy\Tokens\TokenEntity;

class TokenAlreadyUsedException extends TokenException
{
    /**
     * Static constructor.
     *
     * @param TokenEntity $token
     * @return TokenAlreadyUsedException
     */
    public static function fromToken(TokenEntity $token): self
    {
        $exception = new static('Token is already used.');

        $exception->setToken($token);

        return $exception;
    }
}
