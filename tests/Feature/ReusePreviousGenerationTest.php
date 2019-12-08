<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Carbon\CarbonInterval;
use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;

class ReusePreviousGenerationTest extends TestCase
{
    /** @test */
    public function tokens_can_be_generated_second_time_with_reuse_strategy(): void
    {
        $manager = $this->tokenManager();

        $manager->define('sms.verification', [
            'previous' => 'reuse',
            'ttl' => CarbonInterval::minutes(10),
        ]);

        $user = $this->createTokenableEntity();

        $previousToken = $manager->generateFor($user, 'sms.verification');

        $this->freezeTime(now()->addMinutes(5));

        $token = $manager->generateFor($user, 'sms.verification');

        $this->assertTrue($token->is($previousToken));

        $this->assertCount(1, TokenEntity::all());

        $this->assertDatabaseHas('tokens', [
            'expired_at' => now()->addMinutes(10),
        ]);
    }

    /** @test */
    public function token_cannot_be_reused_when_previous_one_is_expired(): void
    {
        $manager = $this->tokenManager();

        $manager->define('sms.verification', [
            'previous' => 'reuse',
            'ttl' => CarbonInterval::minutes(10),
        ]);

        $user = $this->createTokenableEntity();

        $previousToken = $manager->generateFor($user, 'sms.verification');

        $this->freezeTime(now()->addMinutes(15));

        $token = $manager->generateFor($user, 'sms.verification');

        $this->assertFalse($token->is($previousToken));
        $this->assertCount(2, TokenEntity::all());
    }

    /** @test */
    public function token_cannot_be_reused_when_previous_one_belongs_to_another_model(): void
    {
        $manager = $this->tokenManager();

        $manager->define('sms.verification', [
            'previous' => 'reuse',
            'ttl' => CarbonInterval::minutes(10),
        ]);

        $previousToken = $manager->generateFor($this->createTokenableEntity(), 'sms.verification');

        $token = $manager->generateFor($this->createTokenableEntity(), 'sms.verification');

        $this->assertFalse($token->is($previousToken));
        $this->assertCount(2, TokenEntity::all());
    }

    /** @test */
    public function token_cannot_be_reused_when_previous_one_has_different_type(): void
    {
        $manager = $this->tokenManager();

        $manager->define('sms.verification', [
            'previous' => 'reuse',
            'ttl' => CarbonInterval::minutes(10),
        ]);

        $manager->define('verification', [
            'previous' => 'reuse',
            'ttl' => CarbonInterval::minutes(10),
        ]);

        $user = $this->createTokenableEntity();

        $previousToken = $manager->generateFor($user, 'sms.verification');

        $token = $manager->generateFor($user, 'verification');

        $this->assertFalse($token->is($previousToken));
        $this->assertCount(2, TokenEntity::all());
    }

    /** @test */
    public function token_cannot_be_reused_when_previous_one_is_already_used(): void
    {
        $manager = $this->tokenManager();

        $manager->define('sms.verification', [
            'previous' => 'reuse',
            'ttl' => CarbonInterval::minutes(10),
        ]);

        $user = $this->createTokenableEntity();

        $previousToken = $manager->generateFor($user, 'sms.verification');

        $previousToken->markAsUsed();

        $token = $manager->generateFor($user, 'sms.verification');

        $this->assertFalse($token->is($previousToken));
        $this->assertCount(2, TokenEntity::all());
    }
}
