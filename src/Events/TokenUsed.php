<?php

namespace Nevadskiy\Tokens\Events;

use Nevadskiy\Tokens\TokenEntity;
use Nevadskiy\Tokens\Tokens\Token;

class TokenUsed
{
    /**
     * @var TokenEntity
     */
    public $tokenEntity;

    /**
     * @var Token
     */
    public $tokenType;

    /**
     * Create a new event instance.
     *
     * @param TokenEntity $tokenEntity
     * @param Token $tokenType
     */
    public function __construct(TokenEntity $tokenEntity, Token $tokenType)
    {
        $this->tokenEntity = $tokenEntity;
        $this->tokenType = $tokenType;
    }
}
