<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Carbon\CarbonInterval;
use LogicException;
use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;
use Nevadskiy\Tokens\TokenManager;

class TokenTypesTest extends TestCase
{
    /** @test */
    public function unknown_token_types_will_throw_an_exception_during_generation(): void
    {
        try {
            app(TokenManager::class)->generateFor($this->createTokenableEntity(), 'unknown.type');
            $this->fail('Token was generated for unknown token type.');
        } catch (LogicException $e) {
            $this->assertEmpty(TokenEntity::all());
        }
    }

    /** @test */
    public function unknown_token_types_will_throw_an_exception_during_usage(): void
    {
        $token = factory(TokenEntity::class)->create([
            'token' => 'TEST_TOKEN',
            'name' => 'unknown.type',
        ]);

        try {
            app(TokenManager::class)->use('TEST_TOKEN', 'unknown.type', function () {
                $this->fail('Token use callback was called when should not.');
            });
            $this->fail('Token was used for unknown token type.');
        } catch (LogicException $e) {
            $this->assertNull($token->fresh()->used_at);
        }
    }

    /** @test */
    public function different_token_types_can_be_registered_as_valid_token_types(): void
    {
        $manager = $this->tokenManager();

        $this->assertEmpty($manager->getDefined());

        $manager->define('verification');
        $manager->define('magic.link');

        $this->assertCount(2, $manager->getDefined());
    }

    /** @test */
    public function tokens_can_be_registered_with_its_own_options(): void
    {
        $manager = $this->tokenManager();

        $ttl = CarbonInterval::minute();

        $manager->define('custom.type', ['ttl' => $ttl]);

        $this->assertEquals($ttl, $manager->getDefined()['custom.type']['ttl']);
    }
}
