<?php

namespace Nevadskiy\Tokens;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use DateInterval;
use DateTimeInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Nevadskiy\Tokens\Events\TokenCreated;
use Nevadskiy\Tokens\Events\TokenUsed;
use Nevadskiy\Tokens\Exceptions\LockoutException;
use Nevadskiy\Tokens\Exceptions\TokenAccessException;
use Nevadskiy\Tokens\Exceptions\TokenAlreadyUsedException;
use Nevadskiy\Tokens\Exceptions\TokenException;
use Nevadskiy\Tokens\Exceptions\TokenExpiredException;
use Nevadskiy\Tokens\Exceptions\TokenInvalidException;
use Nevadskiy\Tokens\RateLimiter\CacheRateLimiter;
use Nevadskiy\Tokens\RateLimiter\RateLimiter;
use Nevadskiy\Tokens\Repository\TokenRepository;
use Nevadskiy\Tokens\Tokens\GenerationLimit;
use Nevadskiy\Tokens\Tokens\OptionsToken;
use Nevadskiy\Tokens\Tokens\Token;
use Nevadskiy\Tokens\Tokens\UsageLimit;
use RuntimeException;
use UnexpectedValueException;

class TokenManager
{
    /**
     * @var array
     */
    protected $tokens = [];

    /**
     * @var TokenRepository
     */
    protected $repository;

    /**
     * @var CacheRateLimiter
     */
    protected $limiter;

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var int
     */
    protected $generationAttempts;

    /**
     * TokenManager constructor.
     *
     * @param TokenRepository $repository
     * @param RateLimiter $limiter
     * @param Dispatcher $dispatcher
     * @param int $generationAttempts
     */
    public function __construct(
        TokenRepository $repository,
        RateLimiter $limiter,
        Dispatcher $dispatcher,
        int $generationAttempts = 10
    )
    {
        $this->repository = $repository;
        $this->limiter = $limiter;
        $this->generationAttempts = $generationAttempts;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Define a new token with name and options.
     *
     * @param string $tokenName
     * @param array $options
     */
    public function define(string $tokenName, array $options = []): void
    {
        $this->tokens[$tokenName] = $options;
    }

    /**
     * Get all defined by name tokens.
     *
     * @return array
     */
    public function getDefined(): array
    {
        return $this->tokens;
    }

    /**
     * Generate a token entity for the given model.
     *
     * @param Model $model
     * @param Token|string $token
     * @return TokenEntity
     * @throws LockoutException
     */
    public function generateFor(Model $model, $token): TokenEntity
    {
        $token = $this->resolveToken($token);

        if ($this->isTokenGenerationThrottlingEnabled($token)) {
            $this->limiter->attempt(
                $token->getGenerationLimiterKey(),
                $this->getTokenGenerationAttempts($token),
                $this->getGenerationAttemptsInterval($token)
            );
        }

        $tokenEntity = $this->generate($model, $token);

        $this->dispatcher->dispatch(new TokenCreated($tokenEntity, $token));

        return $tokenEntity;
    }

    /**
     * @param Model $model
     * @param Token $token
     * @return TokenEntity
     */
    protected function generate(Model $model, Token $token): TokenEntity
    {
        switch ($token->getGenerationStrategy()) {
            case 'reuse':
                return $this->generateUsingReuseStrategy($token, $model);
            case 'remove':
                return $this->generateUsingRemoveStrategy($token, $model);
            case 'keep':
                return $this->generateUsingKeepStrategy($token, $model);
            default:
                throw new UnexpectedValueException("Provide a valid 'previous' strategy for {$token->getName()} token.");
        }
    }

    /**
     * Generate a token using the 'reuse' previous token strategy.
     *
     * @param Token $token
     * @param Model $model
     * @return TokenEntity
     */
    protected function generateUsingReuseStrategy(Token $token, Model $model): TokenEntity
    {
        $tokenEntity = $this->repository->findActiveByNameFor($model, $token->getName());

        if (! $tokenEntity) {
            return $this->createFreshTokenEntity($token, $model);
        }

        $tokenEntity->continueTo($this->getExpirationDate($token));

        return $tokenEntity;
    }

    /**
     * Generate a token using the 'remove' previous token strategy.
     *
     * @param Token $token
     * @param Model $model
     * @return TokenEntity
     */
    protected function generateUsingRemoveStrategy(Token $token, Model $model): TokenEntity
    {
        $tokenEntity = $this->repository->findActiveByNameFor($model, $token->getName());

        if ($tokenEntity) {
            $tokenEntity->delete();
        }

        return $this->createFreshTokenEntity($token, $model);
    }

    /**
     * Generate a token using the 'keep' previous token strategy.
     *
     * @param Token $token
     * @param Model $model
     * @return TokenEntity
     */
    protected function generateUsingKeepStrategy(Token $token, Model $model): TokenEntity
    {
        return $this->createFreshTokenEntity($token, $model);
    }

    /**
     * Generate the fresh token entity for the given model.
     *
     * @param Token $token
     * @param Model $model
     * @return TokenEntity
     */
    protected function createFreshTokenEntity(Token $token, Model $model): TokenEntity
    {
        return $this->repository->createFor(
            $model,
            $token->getName(),
            $this->generateUniqueTokenString($token),
            $this->getExpirationDate($token)
        );
    }

    /**
     * Use the token according to the provided callback.
     *
     * @param string $tokenString
     * @param Token|string $tokenType
     * @param callable $callback
     * @param Model $owner
     * @return Model
     * @throws TokenException
     */
    public function use($tokenString, $tokenType, callable $callback, Model $owner = null): Model
    {
        $token = $this->resolveToken($tokenType);

        if (! $this->isTokenUsageThrottlingEnabled($token)) {
            return $this->findAndUseToken($tokenString, $token, $callback, $owner);
        }

        return $this->limiter->limit(
            $token->getUsageLimiterKey(),
            $this->getTokenUsageAttempts($token),
            $this->getUsageAttemptsInterval($token),
            function () use ($tokenString, $token, $callback, $owner) {
                return $this->findAndUseToken($tokenString, $token, $callback, $owner);
            }
        );
    }

    /**
     * Use the token of the specified type according to the provided callback.
     *
     * @param string $tokenString
     * @param Token $token
     * @param callable $callback
     * @param Model|null $owner
     * @return Model
     * @throws TokenException
     */
    protected function findAndUseToken($tokenString, Token $token, callable $callback, Model $owner = null): Model
    {
        $this->validateToken($tokenString);

        $tokenEntity = $this->repository->getByTokenAndName($tokenString, $token->getName());

        $this->guardExpiredToken($tokenEntity);
        $this->guardUsedToken($tokenEntity);
        $this->guardTokenOwner($tokenEntity->tokenable, $owner);

        if (false === $callback($tokenEntity->tokenable)) {
            return $tokenEntity->tokenable;
        }

        $tokenEntity->markAsUsed();

        $this->dispatcher->dispatch(new TokenUsed($tokenEntity, $token));

        return $tokenEntity->tokenable;
    }

    /**
     * Use the token of the specified type according to the provided callback with access check applied.
     *
     * @param string $tokenString
     * @param Token|string $tokenType
     * @param Model $owner
     * @param callable $callback
     * @throws TokenException
     */
    public function useFor($tokenString, $tokenType, Model $owner, callable $callback): void
    {
        $this->use($tokenString, $tokenType, $callback, $owner);
    }

    /**
     * Generate a unique token string.
     *
     * @param Token $token
     * @return string
     */
    protected function generateUniqueTokenString(Token $token): string
    {
        $attempts = 0;

        do {
            $this->guardMaxGenerationAttempts($token, $attempts);
            $tokenString = $token->generate();
            $attempts++;
        } while ($this->repository->findByTokenAndName($tokenString, $token->getName()));

        return $tokenString;
    }

    /**
     * Guard max generation attempts.
     *
     * @param Token $token
     * @param int $attempts
     */
    protected function guardMaxGenerationAttempts(Token $token, int $attempts): void
    {
        if ($attempts >= $this->generationAttempts) {
            throw new RuntimeException("Cannot generate a unique token for {$token->getName()} type.");
        }
    }

    /**
     * Generate an expire date for the token according to registered token type.
     *
     * @param Token $token
     * @return Carbon
     */
    protected function getExpirationDate(Token $token): Carbon
    {
        $ttl = $token->getExpirationDate();

        if ($ttl instanceof DateInterval) {
            return Carbon::now()->add($ttl);
        }

        if (is_int($ttl) && $ttl > 0) {
            return Carbon::now()->addMinutes($ttl);
        }

        if ($ttl instanceof DateTimeInterface) {
            $ttl = Carbon::instance($ttl);
        }

        if ($ttl instanceof Carbon && $ttl->isFuture()) {
            return $ttl;
        }

        throw new UnexpectedValueException("Provide a valid ttl option for {$token->getName()} token.");
    }

    /**
     * Resolve a token.
     *
     * @param Token|string $token
     * @return Token
     */
    protected function resolveToken($token): Token
    {
        if ($token instanceof Token) {
            return $token;
        }

        $this->guardUnknownToken($token);

        return app(OptionsToken::class, ['name' => $token, 'options' => $this->tokens[$token]]);
    }

    /**
     * Guard against unknown token.
     *
     * @param string $name
     */
    protected function guardUnknownToken(string $name): void
    {
        if (! isset($this->tokens[$name])) {
            throw new LogicException("Token with name {$name} is not defined.");
        }
    }

    /**
     * Get token generation attempts amount.
     *
     * @param Token|GenerationLimit $token
     * @return int
     */
    protected function getTokenGenerationAttempts(GenerationLimit $token): int
    {
        $attempts = $token->getGenerationAttempts();

        $this->guardInvalidAttempts($attempts);

        return $attempts;
    }

    /**
     * Get token usage attempts amount.
     *
     * @param Token|UsageLimit $token
     * @return int
     */
    protected function getTokenUsageAttempts(UsageLimit $token): int
    {
        $attempts = $token->getUsageAttempts();

        $this->guardInvalidAttempts($attempts);

        return $attempts;
    }

    /**
     * Guard against invalid throttling attempts.
     *
     * @param int $attempts
     */
    protected function guardInvalidAttempts(int $attempts): void
    {
        if ($attempts < 1) {
            throw new UnexpectedValueException('Throttle attempts option should be equal to at least 1.');
        }
    }

    /**
     * Get the token generation attempts interval.
     *
     * @param Token|GenerationLimit $token
     * @return CarbonInterval
     */
    protected function getGenerationAttemptsInterval(GenerationLimit $token): CarbonInterval
    {
        return $this->parseInterval(
            $token->getGenerationAttemptsInterval()
        );
    }

    /**
     * Get the token usage attempts interval.
     *
     * @param Token|UsageLimit $token
     * @return CarbonInterval
     */
    protected function getUsageAttemptsInterval(UsageLimit $token): CarbonInterval
    {
        return $this->parseInterval(
            $token->getUsageAttemptsInterval()
        );
    }

    /**
     * Parse the token interval.
     *
     * @param $interval
     * @return CarbonInterval
     */
    protected function parseInterval($interval): CarbonInterval
    {
        if (is_int($interval) && $interval > 0) {
            return CarbonInterval::minutes($interval);
        }

        if ($interval instanceof DateInterval) {
            return CarbonInterval::instance($interval);
        }

        if ($interval instanceof DateTimeInterface) {
            $interval = Carbon::instance($interval);

            if ($interval->isFuture()) {
                return $interval->diffAsCarbonInterval();
            }
        }

        throw new UnexpectedValueException('Provide correct date interval.');
    }

    /**
     * Guard against already expired token.
     *
     * @param TokenEntity $token
     * @throws TokenExpiredException
     */
    protected function guardExpiredToken(TokenEntity $token): void
    {
        if ($token->isExpired()) {
            throw TokenExpiredException::fromToken($token);
        }
    }

    /**
     * Guard against already used token.
     *
     * @param TokenEntity $token
     * @throws TokenAlreadyUsedException
     */
    protected function guardUsedToken(TokenEntity $token): void
    {
        if ($token->isUsed()) {
            throw TokenAlreadyUsedException::fromToken($token);
        }
    }

    /**
     * Guard against unauthorized token access.
     *
     * @param Model|null $owner
     * @param Model $tokenable
     * @throws TokenAccessException
     */
    protected function guardTokenOwner(Model $owner, ?Model $tokenable): void
    {
        if ($tokenable && $tokenable->isNot($owner)) {
            throw TokenAccessException::fromOwner($owner);
        }
    }

    /**
     * Determine if the token generation throttling is enabled.
     *
     * @param Token $token
     * @return bool
     */
    protected function isTokenGenerationThrottlingEnabled(Token $token): bool
    {
        if (method_exists($token, 'isGenerationThrottlingEnabled')) {
            return $token->isGenerationThrottlingEnabled();
        }

        return $token instanceof GenerationLimit;
    }

    /**
     * Determine if the token usage throttling is enabled.
     *
     * @param Token $token
     * @return bool
     */
    protected function isTokenUsageThrottlingEnabled(Token $token): bool
    {
        if (method_exists($token, 'isUsageThrottlingEnabled')) {
            return $token->isUsageThrottlingEnabled();
        }

        return $token instanceof UsageLimit;
    }

    /**
     * Validate the token string.
     *
     * @param string $token
     * @throws TokenInvalidException
     */
    protected function validateToken($token): void
    {
        if (! $token || ! is_string($token) || mb_strlen($token) > 255) {
            throw new TokenInvalidException('Token is not found.');
        }
    }
}
