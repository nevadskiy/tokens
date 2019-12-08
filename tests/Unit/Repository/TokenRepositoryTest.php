<?php

namespace Nevadskiy\Tokens\Tests\Unit\Repository;

use Nevadskiy\Tokens\Exceptions\TokenNotFoundException;
use Nevadskiy\Tokens\Repository\TokenRepository;
use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;

/**
 * @see TokenRepository
 */
class TokenRepositoryTest extends TestCase
{
    /** @test */
    public function it_returns_token_model_by_token_string_and_name(): void
    {
        $token = factory(TokenEntity::class)->create(['token' => 'SECRET_TOKEN', 'name' => 'test.name']);

        $result = app(TokenRepository::class)->getByTokenAndName('SECRET_TOKEN', 'test.name');

        $this->assertTrue($token->is($result));
    }

    /** @test */
    public function it_returns_the_latest_token(): void
    {
        $token = factory(TokenEntity::class)->create(['token' => 'SECRET_TOKEN', 'name' => 'test.name']);
        $latestToken = factory(TokenEntity::class)->create(['token' => 'SECRET_TOKEN', 'name' => 'test.name']);

        $result = app(TokenRepository::class)->getByTokenAndName('SECRET_TOKEN', 'test.name');

        $this->assertTrue($latestToken->is($result));
    }

    /** @test */
    public function it_throws_an_exception_if_token_is_not_found(): void
    {
        $this->expectException(TokenNotFoundException::class);
        app(TokenRepository::class)->getByTokenAndName('SECRET_TOKEN', 'test.name');
    }

    /** @test */
    public function it_find_tokens_correctly(): void
    {
        $token1 = factory(TokenEntity::class)->create(['token' => 'SECRET_TOKEN', 'name' => 'password.reset']);
        $token2 = factory(TokenEntity::class)->create(['token' => 'SECRET_TOKEN', 'name' => 'magic.link']);
        $token3 = factory(TokenEntity::class)->create(['token' => 'ANOTHER_TOKEN', 'name' => 'password.reset']);

        $result = app(TokenRepository::class)->getByTokenAndName('SECRET_TOKEN', 'magic.link');

        $this->assertTrue($token2->is($result));
    }

    /** @test */
    public function it_does_not_find_token_by_wrong_name(): void
    {
        factory(TokenEntity::class)->create(['token' => 'SECRET_TOKEN', 'name' => 'password.reset']);

        $this->expectException(TokenNotFoundException::class);

        app(TokenRepository::class)->getByTokenAndName('SECRET_TOKEN', 'magic.link');
    }

    /** @test */
    public function it_finds_active_tokens_for_given_models(): void
    {
        $user = $this->createTokenableEntity();

        $tokenForDifferentModel = $this->tokenFactory()->withName('verification')->create();
        $activeToken = $this->tokenFactory()->withName('verification')->for($user)->create();
        $expiredToken = $this->tokenFactory()->withName('verification')->for($user)->expired()->create();
        $usedToken = $this->tokenFactory()->withName('verification')->for($user)->used()->create();
        $anotherToken = $this->tokenFactory()->withName('password')->for($user)->create();

        $token =  app(TokenRepository::class)->findActiveByNameFor($user, 'verification');

        $this->assertTrue($token->is($activeToken));
    }

    /** @test */
    public function it_returns_null_if_active_token_is_not_found_for_given_model(): void
    {
        $user = $this->createTokenableEntity();

        $this->tokenFactory()->withName('password')->for($user)->create();
        $this->tokenFactory()->withName('verification')->for($user)->expired()->create();
        $this->tokenFactory()->withName('verification')->for($user)->used()->create();
        $this->tokenFactory()->withName('verification')->create();

        $this->assertNull(app(TokenRepository::class)->findActiveByNameFor($user, 'verification'));
    }

    /** @test */
    public function it_creates_tokens(): void
    {
        $this->freezeTime();

        $user = $this->createTokenableEntity();

        app(TokenRepository::class)->createFor($user, 'verification', 'TEST_TOKEN', now()->addDay());

        $this->assertCount(1, TokenEntity::all());

        $this->assertDatabaseHas('tokens', [
            'tokenable_type' => get_class($user),
            'tokenable_id' => $user->getKey(),
            'name' => 'verification',
            'token' => 'TEST_TOKEN',
            'expired_at' => now()->addDay(),
        ]);
    }
}
