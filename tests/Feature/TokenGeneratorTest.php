<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Nevadskiy\Tokens\Generator\RandomHashGenerator;
use Nevadskiy\Tokens\Generator\ShortCodeGenerator;
use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;
use UnexpectedValueException;

class TokenGeneratorTest extends TestCase
{
    /** @test */
    public function random_hash_generator_is_used_by_default(): void
    {
        $this->mock(RandomHashGenerator::class)
            ->shouldReceive('generate')
            ->once()
            ->andReturn('RANDOM_HASH_TOKEN');

        $manager = $this->tokenManager();

        $manager->define('magic.link');

        $manager->generateFor($this->createTokenableEntity(), 'magic.link');

        $this->assertDatabaseHas('tokens', [
            'token' => 'RANDOM_HASH_TOKEN',
        ]);
    }

    /** @test */
    public function tokens_can_be_generated_with_provided_generator_class(): void
    {
        $manager = $this->tokenManager();

        $this->mock(ShortCodeGenerator::class)
            ->shouldReceive('generate')
            ->andReturn('SHORT_TOKEN');

        $manager->define('password.reset', [
            'generator' => ShortCodeGenerator::class,
        ]);

        $manager->generateFor($this->createTokenableEntity(), 'password.reset');

        $this->assertEquals(ShortCodeGenerator::class, $manager->getDefined()['password.reset']['generator']);

        $this->assertDatabaseHas('tokens', [
            'token' => 'SHORT_TOKEN',
        ]);
    }

    /** @test */
    public function tokens_can_be_generated_with_provided_generator_object(): void
    {
        $manager = $this->tokenManager();

        $generator = $this->mock(ShortCodeGenerator::class);
        $generator->shouldReceive('generate')->andReturn('TEST_TOKEN');

        $manager->define('password.reset', [
            'generator' => $generator,
        ]);

        $manager->generateFor($this->createTokenableEntity(), 'password.reset');

        $this->assertSame($generator, $manager->getDefined()['password.reset']['generator']);

        $this->assertDatabaseHas('tokens', [
            'token' => 'TEST_TOKEN',
        ]);
    }

    /** @test */
    public function invalid_generator_option_throws_an_exception_during_generation(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('magic.link', [
            'generator' => 'INVALID_GENERATOR'
        ]);

        try {
            $manager->generateFor($this->createTokenableEntity(), 'magic.link');
            $this->fail('Token by invalid generator was generated when should not.');
        } catch (UnexpectedValueException $e) {
            $this->assertEmpty(TokenEntity::all());
        }
    }
}
