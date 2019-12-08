<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Illuminate\Events\Dispatcher;
use Nevadskiy\Tokens\Generator\RandomHashGenerator;
use Nevadskiy\Tokens\Generator\ShortCodeGenerator;
use Nevadskiy\Tokens\RateLimiter\CacheRateLimiter;
use Nevadskiy\Tokens\Repository\TokenRepository;
use Nevadskiy\Tokens\Tests\Support\Models\User;
use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;
use Nevadskiy\Tokens\TokenManager;
use RuntimeException;

class TokenGenerationTest extends TestCase
{
    /** @test */
    public function tokens_can_be_generated_by_default_generator(): void
    {
        $tokenString = app(RandomHashGenerator::class)->generate();

        $this->mock(RandomHashGenerator::class)
            ->shouldReceive('generate')
            ->once()
            ->andReturn($tokenString);

        $user = factory(User::class)->create();

        $manager = $this->tokenManager();

        $manager->define('verification');

        $manager->generateFor($user, 'verification');

        $this->assertDatabaseHas('tokens', [
            'name' => 'verification',
            'token' => $tokenString,
        ]);
    }

    /** @test */
    public function tokens_can_be_generated_for_given_models(): void
    {
        $user = factory(User::class)->create();

        $manager = $this->tokenManager();

        $manager->define('magic.link');

        $token = $manager->generateFor($user, 'magic.link');

        $this->assertTrue($token->tokenable->is($user));

        $this->assertDatabaseHas('tokens', [
            'name' => 'magic.link',
            'tokenable_id' => $user->id,
            'tokenable_type' => get_class($user),
        ]);
    }

    /** @test */
    public function tokens_are_generated_with_nullable_used_at_attribute(): void
    {
        $manager = $this->tokenManager();

        $manager->define('magic.link');

        $token = $manager->generateFor($this->createTokenableEntity(), 'magic.link');

        $this->assertNull($token->used_at);
    }

    /** @test */
    public function only_unique_tokens_will_be_generated_within_single_type(): void
    {
        $generator = $this->mock(ShortCodeGenerator::class);
        $generator->shouldReceive('generate')->once()->andReturn('EXISTING_CODE');
        $generator->shouldReceive('generate')->once()->andReturn('EXISTING_CODE');
        $generator->shouldReceive('generate')->once()->andReturn('DIFFERENT_CODE');

        $this->tokenFactory()->withName('verification')->create('EXISTING_CODE');

        $manager = $this->tokenManager();

        $manager->define('verification', [
            'generator' => $generator
        ]);

        $manager->generateFor($this->createTokenableEntity(), 'verification');

        $this->assertCount(2, TokenEntity::all());

        $this->assertDatabaseHas('tokens', [
            'token' => 'DIFFERENT_CODE',
        ]);

        $this->assertDatabaseHas('tokens', [
            'token' => 'EXISTING_CODE',
        ]);
    }

    /** @test */
    public function generator_throws_an_exception_when_it_cannot_generate_a_unique_token_within_available_attempts(): void
    {
        $generator = $this->mock(ShortCodeGenerator::class);
        $generator->shouldReceive('generate')->times(3)->andReturn('NOT_UNIQUE_TOKEN');

        $this->tokenFactory()->withName('verification')->create('NOT_UNIQUE_TOKEN');

        $manager = new TokenManager(app(TokenRepository::class), app(CacheRateLimiter::class), app(Dispatcher::class), 3);

        $manager->define('verification', [
            'generator' => $generator
        ]);

        try {
            $manager->generateFor($this->createTokenableEntity(), 'verification');
            $this->fail('Generator generates not unique tokens instead of throwing an exception.');
        } catch (RuntimeException $e) {
            $this->assertCount(1, TokenEntity::all());
        }
    }

    /** @test */
    public function tokens_are_unique_only_within_single_type(): void
    {
        $generator = $this->mock(ShortCodeGenerator::class);
        $generator->shouldReceive('generate')->once(3)->andReturn('NOT_UNIQUE_TOKEN');

        $this->tokenFactory()->withName('password.reset')->create('NOT_UNIQUE_TOKEN');

        $manager = $this->tokenManager();

        $manager->define('verification', [
            'generator' => $generator
        ]);

        $manager->generateFor($this->createTokenableEntity(), 'verification');
        $this->assertCount(2, TokenEntity::all());
    }
}
