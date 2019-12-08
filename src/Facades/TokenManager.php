<?php

namespace Nevadskiy\Tokens\Facades;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;
use Nevadskiy\Tokens\TokenEntity;
use Nevadskiy\Tokens\TokenManager as BaseTokenManager;
use Nevadskiy\Tokens\Tokens\Token;

/**
 * @method static TokenEntity generateFor(Model $model, Token|string $token):
 * @method static Model use(string $tokenString, Token|string $tokenType, callable $callback, Model $owner = null)
 * @method static Model useFor(string $tokenString, Token|string $tokenType, Model $owner, callable $callback)
 * @method static void define(string $tokenName, array $options = [])
 * @method static array getDefined()
 *
 * @see BaseTokenManager
 */
class TokenManager extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return BaseTokenManager::class;
    }
}
