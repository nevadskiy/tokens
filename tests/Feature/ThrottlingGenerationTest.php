<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Nevadskiy\Tokens\Exceptions\LockoutException;
use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;
use UnexpectedValueException;

class ThrottlingGenerationTest extends TestCase
{
    /** @test */
    public function tokens_can_be_generated_with_limited_generation_attempts(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'previous' => 'keep',
            'generation_attempts' => 2,
            'generation_attempts_interval' => 10,
            'generation_throttle' => true,
        ]);

        $user = $this->createTokenableEntity();

        try {
            for ($i = 1; $i <= 3; $i++) {
                $manager->generateFor($user, 'reset.password');
            }
            $manager->generateFor($user, 'reset.password');
            $this->fail('Token was generated 3rd time but RateLimiter should limit attempts.');
        } catch (LockoutException $e) {
            $this->assertCount(2, TokenEntity::all());
            $this->assertEquals(now()->addMinutes(10), $e->getUnlockTime());
        }
    }

    /** @test */
    public function tokens_use_3_generated_attempts_by_default(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'previous' => 'keep',
            'generation_attempts_interval' => 10,
        ]);

        $user = $this->createTokenableEntity();

        try {
            for ($i = 1; $i <= 4; $i++) {
                $manager->generateFor($user, 'reset.password');
            }
            $this->fail('Token was generated 4th time but RateLimiter should limit attempts.');
        } catch (LockoutException $e) {
            $this->assertCount(3, TokenEntity::all());
            $this->assertEquals(now()->addMinutes(10), $e->getUnlockTime());
        }
    }

    /** @test */
    public function tokens_generation_attempts_timeout_is_10_minutes_by_default(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('reset.password');

        $user = $this->createTokenableEntity();

        try {
            for ($i = 1; $i <= 4; $i++) {
                $manager->generateFor($user, 'reset.password');
            }
        } catch (LockoutException $e) {
            $this->assertEquals(now()->addMinutes(10), $e->getUnlockTime());
        }
    }

    /** @test */
    public function tokens_can_be_generated_again_after_timeout(): void
    {
        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'previous' => 'keep',
            'generation_attempts' => 1,
            'generation_attempts_interval' => 10,
        ]);

        $user = $this->createTokenableEntity();

        try {
            for ($i = 1; $i <= 2; $i++) {
                $manager->generateFor($user, 'reset.password');
            }
            $this->fail('Token has been generated over limited attempts.');
        } catch (LockoutException $e) {
            $this->assertCount(1, TokenEntity::all());
        }

        $this->freezeTime(now()->addMinutes(11));

        $manager->generateFor($user, 'reset.password');

        $this->assertCount(2, TokenEntity::all());
    }

    /** @test */
    public function tokens_can_be_generated_over_max_attempts_times_after_timeout(): void
    {
        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'previous' => 'keep',
            'generation_attempts' => 1,
            'generation_attempts_interval' => 10,
        ]);

        $user = $this->createTokenableEntity();

        $manager->generateFor($user, 'reset.password');

        $this->freezeTime(now()->addMinutes(11));

        $manager->generateFor($user, 'reset.password');

        $this->assertCount(2, TokenEntity::all());
    }

    /** @test */
    public function tokens_generation_throttling_works_only_within_single_token_type(): void
    {
        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'generation_attempts' => 1,
            'generation_attempts_interval' => 1,
        ]);

        $manager->define('verification', [
            'generation_attempts' => 1,
            'generation_attempts_interval' => 1,
        ]);

        $user = $this->createTokenableEntity();

        $manager->generateFor($user, 'reset.password');
        $manager->generateFor($user, 'verification');

        $this->assertCount(2, TokenEntity::all());
    }

    /** @test */
    public function tokens_generation_attempts_must_be_at_least_1(): void
    {
        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'generation_attempts' => 0,
        ]);

        try {
            $manager->generateFor($this->createTokenableEntity(), 'reset.password');
            $this->fail('Token has been generated with 0 throttling attempts option');
        } catch (UnexpectedValueException $e) {
            $this->assertEmpty(TokenEntity::all());
        }
    }

    /** @test */
    public function generation_attempts_interval_is_equal_to_10_by_default(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'previous' => 'keep',
            'generation_attempts' => 1,
        ]);

        $user = $this->createTokenableEntity();

        $manager->generateFor($user, 'reset.password');

        $this->freezeTime(now()->addMinutes(10)->addSecond());

        $manager->generateFor($user, 'reset.password');

        try {
            $this->freezeTime(now()->addMinutes(10));
            $manager->generateFor($user, 'reset.password');
            $this->fail('Token has been generated when timeout was not passed.');
        } catch (LockoutException $e) {
            $this->assertCount(2, TokenEntity::all());
        }
    }

    /** @test */
    public function generation_throttling_can_be_disabled(): void
    {
        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'previous' => 'keep',
            'generation_throttling' => false,
            'generation_attempts' => 1,
        ]);

        $user = $this->createTokenableEntity();

        $manager->generateFor($user, 'reset.password');
        $manager->generateFor($user, 'reset.password');

        $this->assertCount(2, TokenEntity::all());
    }
}
