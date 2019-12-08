<?php

namespace Nevadskiy\Tokens\Tests\Unit;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Nevadskiy\Tokens\Tests\Support\Models\User;
use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;

/**
 * @see TokenEntity
 */
class TokenEntityTest extends TestCase
{
    /** @test */
    public function it_has_tokenable_relation(): void
    {
        $user = factory(User::class)->create();

        $token = factory(TokenEntity::class)->create([
            'tokenable_type' => get_class($user),
            'tokenable_id' => $user->id,
        ]);

        $this->assertTrue($token->tokenable->is($user));
    }

    /** @test */
    public function it_can_fill_tokenable_attributes_for_models(): void
    {
        $user = factory(User::class)->create();

        $token = factory(TokenEntity::class)->make();
        $token->fillTokenable($user);
        $token->save();

        $this->assertTrue($token->tokenable->is($user));
    }

    /** @test */
    public function it_has_used_at_timestamp(): void
    {
        $now = $this->freezeTime();

        $token = factory(TokenEntity::class)->create(['used_at' => now()]);

        $this->assertInstanceOf(DateTimeInterface::class, $token->used_at);
        $this->assertEquals($now, $token->used_at);
    }

    /** @test */
    public function it_has_expired_at_timestamp(): void
    {
        $now = $this->freezeTime();

        $token = factory(TokenEntity::class)->create(['expired_at' => now()]);

        $this->assertInstanceOf(DateTimeInterface::class, $token->expired_at);
        $this->assertEquals($now, $token->expired_at);
    }

    /** @test */
    public function it_can_be_marked_as_used(): void
    {
        $now = $this->freezeTime();

        $token = factory(TokenEntity::class)->create(['used_at' => null]);

        $this->assertNull($token->used_at);

        $token->markAsUsed();

        $this->assertEquals($now, $token->fresh()->used_at);
    }

    /** @test */
    public function it_can_be_used_as_string(): void
    {
        $token = factory(TokenEntity::class)->make(['token' => 'TEST_TOKEN']);

        $this->assertEquals('TEST_TOKEN', $token);
    }

    /** @test */
    public function it_knows_if_it_is_expired(): void
    {
        $activeToken = factory(TokenEntity::class)->make(['expired_at' => now()->addMinute()]);
        $expiredToken = factory(TokenEntity::class)->make(['expired_at' => now()->subMinute()]);

        $this->assertFalse($activeToken->isExpired());
        $this->assertTrue($expiredToken->isExpired());
    }

    /** @test */
    public function it_knows_if_it_is_already_used(): void
    {
        $activeToken = factory(TokenEntity::class)->make(['used_at' => null]);
        $usedToken = factory(TokenEntity::class)->make(['used_at' => now()->subMinute()]);

        $this->assertFalse($activeToken->isUsed());
        $this->assertTrue($usedToken->isUsed());
    }

    /** @test */
    public function it_can_be_scoped_by_active_tokens(): void
    {
        $used = $this->tokenFactory()->withName('verification')->used()->create();
        $active = $this->tokenFactory()->withName('verification')->create();
        $expired = $this->tokenFactory()->withName('verification')->expired()->create();
        $usedExpired = $this->tokenFactory()->withName('password')->used()->expired()->create();

        $tokens = TokenEntity::active()->get();

        $this->assertCount(1, $tokens);
        $this->assertTrue($tokens[0]->is($active));
    }

    /** @test */
    public function it_can_be_scoped_for_tokenable_tokens(): void
    {
        $user = $this->createTokenableEntity();

        $token1 = $this->tokenFactory()->for($user)->create();
        $token2 = $this->tokenFactory()->create();
        $token3 = $this->tokenFactory()->for($user)->create();
        $token4 = $this->tokenFactory()->create();

        $tokens = TokenEntity::forTokenable($user)->get();

        $this->assertCount(2, $tokens);
        $this->assertTrue($tokens->contains($token1));
        $this->assertTrue($tokens->contains($token3));
    }

    /** @test */
    public function it_can_be_continued_to_the_given_date(): void
    {
        $this->freezeTime();

        $token = factory(TokenEntity::class)->create(['expired_at' => now()->addMinute()]);

        $this->assertEquals(now()->addMinute(), $token->expired_at);

        $token->continueTo(now()->addMonth());

        $this->assertEquals(now()->addMonth(), $token->fresh()->expired_at);
    }

    /** @test */
    public function it_returns_last_generated_token(): void
    {
        $token1 = factory(TokenEntity::class)->create();
        $token2 = factory(TokenEntity::class)->create();
        $token3 = factory(TokenEntity::class)->create();

        $this->assertTrue(TokenEntity::last()->is($token3));
    }

    /** @test */
    public function it_can_be_scoped_by_dead_tokens(): void
    {
        $token1 = factory(TokenEntity::class)->create([
            'expired_at' => now()->subMinute(),
        ]);

        $token2 = factory(TokenEntity::class)->create([
            'used_at' => now()->subHour(),
        ]);

        $token3 = factory(TokenEntity::class)->create();

        $token4 = factory(TokenEntity::class)->create();
        $token4->delete();

        /** @var Collection $tokens */
        $tokens = TokenEntity::dead()->get();

        $this->assertCount(3, $tokens);
        $this->assertTrue($tokens->contains($token1));
        $this->assertTrue($tokens->contains($token2));
        $this->assertTrue($tokens->contains($token4));
    }
}
