<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Carbon\CarbonInterval;
use DateInterval;
use DateTimeInterface;
use Nevadskiy\Tokens\Exceptions\LockoutException;
use Nevadskiy\Tokens\Exceptions\TokenNotFoundException;
use Nevadskiy\Tokens\Tokens\GenerationLimit;
use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\Tokens\Token;
use Nevadskiy\Tokens\TokenEntity;
use Nevadskiy\Tokens\Tokens\UsageLimit;

class ClassBasedTokensTest extends TestCase
{
    /** @test */
    public function tokens_can_be_generated_from_token_object_without_registration_in_manager(): void
    {
        $this->freezeTime();

        $user = $this->createTokenableEntity();

        $previousToken = $this->tokenFactory()->withName('reset.password')->for($user)->create();

        $token = $this->tokenManager()->generateFor($user, new ResetPasswordToken());

        $this->assertTrue($previousToken->fresh()->trashed());
        $this->assertCount(1, TokenEntity::all());

        $this->assertDatabaseHas('tokens', [
            'name' => 'reset.password',
            'token' => 'TEST_TOKEN',
        ]);

        $this->assertEquals(now()->addMonth(), $token->expired_at);
        $this->assertTrue($token->tokenable->is($user));
    }

    /** @test */
    public function tokens_can_be_generated_with_generation_throttling_enabled(): void
    {
        $this->freezeTime();

        $user = $this->createTokenableEntity();

        $this->tokenManager()->generateFor($user, new ResetPasswordGenerationLimit());

        try {
            $this->tokenManager()->generateFor($user, new ResetPasswordGenerationLimit());
            $this->fail('Token was attempt too many times without exception.');
        } catch (LockoutException $e) {
            $this->assertCount(1, TokenEntity::all());
            $this->assertEquals(now()->addMinutes(10), $e->getUnlockTime());
        }
    }

    /** @test */
    public function tokens_can_be_used_with_usage_throttling_enabled(): void
    {
        $this->freezeTime();

        try {
            $this->tokenManager()->use('SECRET_TOKEN', new ResetPasswordUsageLimit(), function () {
                $this->fail('Token usage callback was called when should not.');
            });
        } catch (TokenNotFoundException $e) {
        }

        try {
            try {
                $this->tokenManager()->use('SECRET_TOKEN', new ResetPasswordUsageLimit(), function () {
                    $this->fail('Token usage callback was called when should not.');
                });
            } catch (TokenNotFoundException $e) {
            }
        } catch (LockoutException $e) {
            $this->assertEquals(now()->addMinutes(10), $e->getUnlockTime());
        }
    }
}

class ResetPasswordToken implements Token
{
    /**
     * Get the token name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'reset.password';
    }

    /**
     * Generate a token string.
     *
     * @return string
     */
    public function generate(): string
    {
        return 'TEST_TOKEN';
    }

    /**
     * Get the token expiration date.
     *
     * @return DateInterval|DateTimeInterface|int
     */
    public function getExpirationDate()
    {
        return CarbonInterval::month();
    }

    /**
     * Get the token generation strategy name.
     * Can be one of ['remove', 'keep', 'reuse'].
     *
     * @return string
     */
    public function getGenerationStrategy(): string
    {
        return 'remove';
    }
}

class ResetPasswordGenerationLimit implements Token, GenerationLimit
{
    /**
     * Get the token name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'reset.password';
    }

    /**
     * Generate a token string.
     *
     * @return string
     */
    public function generate(): string
    {
        return 'TEST_TOKEN';
    }

    /**
     * Get the token expiration date.
     *
     * @return DateInterval|DateTimeInterface|int
     */
    public function getExpirationDate()
    {
        return CarbonInterval::month();
    }

    /**
     * Get the token generation strategy name.
     * Can be one of ['remove', 'keep', 'reuse'].
     *
     * @return string
     */
    public function getGenerationStrategy(): string
    {
        return 'remove';
    }

    /**
     * Get the key for identifying attempts for throttling limiter on generation process.
     *
     * @return string
     */
    public function getGenerationLimiterKey(): string
    {
        return 'gen-key';
    }

    /**
     * Get maximum token generation attempts amount for throttling limiter.
     *
     * @return int
     */
    public function getGenerationAttempts(): int
    {
        return 1;
    }

    /**
     * Get the time interval limited generation attempts can be exhausted within.
     *
     * @return DateInterval|DateTimeInterface|int
     */
    public function getGenerationAttemptsInterval()
    {
        return now()->addMinutes(10);
    }
}

class ResetPasswordUsageLimit implements Token, UsageLimit
{
    /**
     * Get the token name.
     *
     * @return string
     */
    public function getName(): string
    {
        return 'reset.password';
    }

    /**
     * Generate a token string.
     *
     * @return string
     */
    public function generate(): string
    {
        return 'TEST_TOKEN';
    }

    /**
     * Get the token expiration date.
     *
     * @return DateInterval|DateTimeInterface|int
     */
    public function getExpirationDate()
    {
        return CarbonInterval::month();
    }

    /**
     * Get the token generation strategy name.
     * Can be one of ['remove', 'keep', 'reuse'].
     *
     * @return string
     */
    public function getGenerationStrategy(): string
    {
        return 'remove';
    }

    /**
     * Get the key for identifying attempts for throttling limiter on usage process.
     *
     * @return string
     */
    public function getUsageLimiterKey(): string
    {
        return 'use-gen';
    }

    /**
     * Get maximum token usage attempts amount for throttling limiter.
     *
     * @return int
     */
    public function getUsageAttempts(): int
    {
        return 1;
    }

    /**
     * Get the time interval limited usage attempts can be exhausted within.
     *
     * @return DateInterval|DateTimeInterface|int
     */
    public function getUsageAttemptsInterval()
    {
        return 10;
    }
}
