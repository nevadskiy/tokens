<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Carbon\CarbonInterval;
use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;

class RemovePreviousGenerationTest extends TestCase
{
    /** @test */
    public function remove_strategy_removes_previous_active_tokens_during_generation_process(): void
    {
        $manager = $this->tokenManager();

        $manager->define('sms.verification', [
            'previous' => 'remove',
            'ttl' => CarbonInterval::minutes(10),
        ]);

        $user = $this->createTokenableEntity();

        $previousToken = $manager->generateFor($user, 'sms.verification');

        $this->freezeTime(now()->addMinutes(5));

        $token = $manager->generateFor($user, 'sms.verification');

        $this->assertTrue($previousToken->fresh()->trashed());
        $this->assertCount(1, TokenEntity::all());
        $this->assertEquals(now(), $token->created_at);
    }

    /** @test */
    public function remove_strategy_does_not_remove_previous_expired_tokens(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('sms.verification', [
            'previous' => 'remove',
        ]);

        $user = $this->createTokenableEntity();

        $previousToken = $this->tokenFactory()->withName('sms.verification')->for($user)->expired()->create();

        $token = $manager->generateFor($user, 'sms.verification');

        $this->assertFalse($previousToken->fresh()->trashed());
        $this->assertCount(2, TokenEntity::all());
        $this->assertEquals(now(), $token->created_at);
    }

    /** @test */
    public function remove_strategy_does_not_remove_previous_tokens_which_belong_to_another_model(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('sms.verification', [
            'previous' => 'remove',
        ]);

        $user = $this->createTokenableEntity();

        $previousToken = $this->tokenFactory()->withName('sms.verification')->create();

        $token = $manager->generateFor($user, 'sms.verification');

        $this->assertFalse($previousToken->fresh()->trashed());
        $this->assertCount(2, TokenEntity::all());
        $this->assertEquals(now(), $token->created_at);
    }

    /** @test */
    public function remove_strategy_does_not_remove_previous_tokens_which_have_different_type(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('sms.verification', [
            'previous' => 'remove',
        ]);

        $user = $this->createTokenableEntity();

        $previousToken = $this->tokenFactory()->withName('verification')->for($user)->create();

        $token = $manager->generateFor($user, 'sms.verification');

        $this->assertFalse($previousToken->fresh()->trashed());
        $this->assertCount(2, TokenEntity::all());
        $this->assertEquals(now(), $token->created_at);
    }

    /** @test */
    public function remove_strategy_does_not_remove_previous_tokens_which_are_already_used(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('sms.verification', [
            'previous' => 'remove',
        ]);

        $user = $this->createTokenableEntity();

        $previousToken = $this->tokenFactory()->withName('sms.verification')->for($user)->used()->create();

        $token = $manager->generateFor($user, 'sms.verification');

        $this->assertFalse($previousToken->fresh()->trashed());
        $this->assertCount(2, TokenEntity::all());
        $this->assertEquals(now(), $token->created_at);
    }
}
