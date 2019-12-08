<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Nevadskiy\Tokens\Exceptions\TokenExpiredException;
use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;
use UnexpectedValueException;

class TokenLifetimeTest extends TestCase
{
    /** @test */
    public function tokens_are_generated_with_month_lifetime_by_default(): void
    {
        $startTime = $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('magic.link');

        $token = $manager->generateFor($this->createTokenableEntity(), 'magic.link');

        $this->freezeTime(now()->addMinutes(43200));

        $this->assertFalse($token->isExpired());

        $this->freezeTime(now()->addSeconds(1));

        $this->assertTrue($token->isExpired());

        $this->assertDatabaseHas('tokens', [
            'expired_at' => $startTime->addMinutes(43200),
        ]);
    }

    /** @test */
    public function tokens_can_be_generated_with_provided_lifetime(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('magic.link', [
            'ttl' => CarbonInterval::minute(),
        ]);

        $token = $manager->generateFor($this->createTokenableEntity(), 'magic.link');

        $this->assertFalse($token->isExpired());

        $this->assertDatabaseHas('tokens', [
            'expired_at' => now()->addMinute(),
        ]);
    }

    /** @test */
    public function ttl_option_can_be_provided_as_amount_of_minutes(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('magic.link', [
            'ttl' => 60,
        ]);

        $manager->generateFor($this->createTokenableEntity(), 'magic.link');

        $this->assertDatabaseHas('tokens', [
            'expired_at' => now()->addMinutes(60),
        ]);
    }

    /** @test */
    public function ttl_option_can_be_provided_as_carbon_instance(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('magic.link', [
            'ttl' => Carbon::now()->addHours(2),
        ]);

        $manager->generateFor($this->createTokenableEntity(), 'magic.link');

        $this->assertDatabaseHas('tokens', [
            'expired_at' => now()->addHours(2),
        ]);
    }

    /** @test */
    public function date_in_the_past_as_ttl_throws_and_exception_during_generation(): void
    {
        $manager = $this->tokenManager();

        $manager->define('magic.link', [
            'ttl' => Carbon::now()->subMinutes(10),
        ]);

        try {
            $manager->generateFor($this->createTokenableEntity(), 'magic.link');
            $this->fail('Token with invalid TTL was generated when should not.');
        } catch (UnexpectedValueException $e) {
            $this->assertEmpty(TokenEntity::all());
        }
    }

    /** @test */
    public function negative_minutes_amount_as_ttl_throws_and_exception_during_generation(): void
    {
        $manager = $this->tokenManager();

        $manager->define('magic.link', [
            'ttl' => -15,
        ]);

        try {
            $manager->generateFor($this->createTokenableEntity(), 'magic.link');
            $this->fail('Token with invalid TTL was generated when should not.');
        } catch (UnexpectedValueException $e) {
            $this->assertEmpty(TokenEntity::all());
        }
    }

    /** @test */
    public function expired_tokens_cannot_be_used(): void
    {
        $manager = $this->tokenManager();

        $manager->define('magic.link');

        $token = factory(TokenEntity::class)->create([
            'token' => 'TEST_TOKEN',
            'name' => 'magic.link',
            'expired_at' => now()->subMinute(),
        ]);

        try {
            $manager->use('TEST_TOKEN', 'magic.link', function () {
                $this->fail('Token callback was called when should not.');
            });
            $this->fail('Token was used when it was expired.');
        } catch (TokenExpiredException $e) {
            $this->assertTrue($e->getToken()->is($token));
            $this->assertNull($token->fresh()->used_at);
        }
    }

    /** @test */
    public function invalid_ttl_value_throws_an_exception_during_generation(): void
    {
        $manager = $this->tokenManager();

        $manager->define('magic.link', [
            'ttl' => 'INVALID_TTL'
        ]);

        try {
            $manager->generateFor($this->createTokenableEntity(), 'magic.link');
            $this->fail('Token with invalid TTL was generated when should not.');
        } catch (UnexpectedValueException $e) {
            $this->assertEmpty(TokenEntity::all());
        }
    }
}
