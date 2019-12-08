<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;
use UnexpectedValueException;

class PreviousOptionTest extends TestCase
{
    /** @test */
    public function previous_option_uses_remove_strategy_by_default(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('password.reset');

        $user = $this->createTokenableEntity();

        $previousToken = $manager->generateFor($user, 'password.reset');
        $token = $manager->generateFor($user, 'password.reset');

        $this->assertTrue($previousToken->fresh()->trashed());
        $this->assertCount(1, TokenEntity::all());
        $this->assertEquals(now(), $token->created_at);
    }

    /** @test */
    public function invalid_previous_option_value_throws_an_exception_during_generation(): void
    {
        $manager = $this->tokenManager();

        $manager->define('magic.link', [
            'previous' => 'update'
        ]);

        try {
            $manager->generateFor($this->createTokenableEntity(), 'magic.link');
            $this->fail('Token with invalid previous strategy was generated when should not.');
        } catch (UnexpectedValueException $e) {
            $this->assertEmpty(TokenEntity::all());
        }
    }
}
