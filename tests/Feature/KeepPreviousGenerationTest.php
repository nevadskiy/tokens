<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;

class KeepPreviousGenerationTest extends TestCase
{
    /** @test */
    public function keep_previous_strategy_does_not_touch_previous_active_tokens_during_generation_process(): void
    {
        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'previous' => 'keep'
        ]);

        $user = $this->createTokenableEntity();

        $previousToken = $manager->generateFor($user, 'reset.password');

        $this->freezeTime(now()->addMinutes(5));

        $token = $manager->generateFor($user, 'reset.password');

        $this->assertTrue($token->isNot($previousToken));
        $this->assertCount(2, TokenEntity::all());
    }
}
