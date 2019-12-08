<?php

namespace Nevadskiy\Tokens\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Nevadskiy\Tokens\Events\TokenCreated;
use Nevadskiy\Tokens\Events\TokenUsed;
use Nevadskiy\Tokens\Exceptions\TokenInvalidException;
use Nevadskiy\Tokens\Generator\RandomHashGenerator;
use Nevadskiy\Tokens\Repository\TokenRepository;
use Nevadskiy\Tokens\Tests\Support\Models\User;
use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;
use Nevadskiy\Tokens\TokenManager;

/**
 * @see TokenManager
 */
class TokenManagerTest extends TestCase
{
    /** @test */
    public function it_is_bound_as_a_singleton(): void
    {
        $this->assertSame(app(TokenManager::class), app(TokenManager::class));
    }

    /** @test */
    public function it_can_add_token_names(): void
    {
        $manager = $this->tokenManager();

        $this->assertCount(0, $manager->getDefined());

        $manager->define('TOKEN_TYPE');

        $this->assertCount(1, $manager->getDefined());
    }

    /** @test */
    public function it_passes_correct_model_to_callback_in_use_method(): void
    {
        $user = factory(User::class)->make();
        $token = factory(TokenEntity::class)->make();
        $token->setRelation('tokenable', $user);

        $this->mock(TokenRepository::class)
            ->shouldReceive('getByTokenAndName')
            ->with('TOKEN_STRING', 'TOKEN_TYPE')
            ->andReturn($token);

        $manager = $this->tokenManager();

        $manager->define('TOKEN_TYPE');

        $manager->use('TOKEN_STRING', 'TOKEN_TYPE', function (User $u) use ($user) {
            $this->assertTrue($u->is($user));
        });
    }

    /** @test */
    public function it_marks_token_as_used_after_using(): void
    {
        $now = $this->freezeTime();

        $token = factory(TokenEntity::class)->create(['used_at' => null]);

        $this->mock(TokenRepository::class)
            ->shouldReceive('getByTokenAndName')
            ->with('TOKEN_STRING', 'TOKEN_TYPE')
            ->andReturn($token);

        $manager = $this->tokenManager();

        $manager->define('TOKEN_TYPE');

        $manager->use('TOKEN_STRING', 'TOKEN_TYPE', function () {});

        $this->assertEquals($now, $token->used_at);
    }

    /** @test */
    public function it_returns_tokenable_model_after_using(): void
    {
        $user = factory(User::class)->make();
        $token = factory(TokenEntity::class)->make();
        $token->setRelation('tokenable', $user);

        $this->mock(TokenRepository::class)
            ->shouldReceive('getByTokenAndName')
            ->with('TOKEN_STRING', 'TOKEN_TYPE')
            ->andReturn($token);

        $manager = $this->tokenManager();

        $manager->define('TOKEN_TYPE');

        $tokenable = $manager->use('TOKEN_STRING', 'TOKEN_TYPE', function () {});

        $this->assertSame($user, $tokenable);
    }

    /** @test */
    public function it_handle_use_for_method_correctly(): void
    {
        $now = $this->freezeTime();

        $user = factory(User::class)->make();
        $token = factory(TokenEntity::class)->create(['used_at' => null]);
        $token->setRelation('tokenable', $user);

        $this->mock(TokenRepository::class)
            ->shouldReceive('getByTokenAndName')
            ->with('SUPER_SECRET_TOKEN', 'TOKEN_TYPE')
            ->andReturn($token);

        $manager = $this->tokenManager();

        $manager->define('TOKEN_TYPE');

        $manager->useFor('SUPER_SECRET_TOKEN', 'TOKEN_TYPE', $user, function (User $u) use ($user) {
            $this->assertSame($user, $u);
        });

        $this->assertEquals($now, $token->fresh()->used_at);
    }

    /** @test */
    public function it_resolves_correct_token_generator(): void
    {
        $manager = $this->tokenManager();

        $this->mock(RandomHashGenerator::class)
            ->shouldReceive('generate')
            ->once()
            ->andReturn('GENERATED_TOKEN');

        $manager->define('verification');

        $token = $manager->generateFor(factory(User::class)->create(), 'verification');

        $this->assertEquals('GENERATED_TOKEN', $token);
    }

    /** @test */
    public function it_throws_an_exception_if_token_is_invalid(): void
    {
        $manager = $this->tokenManager();

        $manager->define('verification');

        $this->expectException(TokenInvalidException::class);

        $manager->use(null, 'verification', function () {
            $this->fail('Invalid token was used.');
        });
    }

    /** @test */
    public function it_fires_event_when_token_is_generated(): void
    {
        Event::fake();

        $manager = $this->tokenManager();

        $manager->define('verification');

        $token = $manager->generateFor($this->createTokenableEntity(), 'verification');

        Event::assertDispatched(TokenCreated::class, function (TokenCreated $event) use ($token) {
            return $event->tokenEntity->is($token)
                && $event->tokenType->getName() === 'verification';
        });
    }

    /** @test */
    public function it_fires_event_when_token_is_used(): void
    {
        Event::fake();

        $token = $this->tokenFactory()->withName('verification')->create('SECRET_TOKEN');

        $manager = $this->tokenManager();

        $manager->define('verification');

        $manager->use('SECRET_TOKEN', 'verification', function () {});

        Event::assertDispatched(TokenUsed::class, function (TokenUsed $event) use ($token) {
            return $event->tokenEntity->is($token)
                && $event->tokenType->getName() === 'verification';
        });
    }
}
