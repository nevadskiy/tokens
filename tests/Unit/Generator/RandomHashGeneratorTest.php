<?php

namespace Nevadskiy\Tokens\Tests\Unit\Generator;

use Nevadskiy\Tokens\Generator\RandomHashGenerator;
use Nevadskiy\Tokens\Tests\TestCase;

/**
 * @see RandomHashGenerator
 */
class RandomHashGeneratorTest extends TestCase
{
    /** @test */
    public function it_generates_token_with_length_of_64_characters(): void
    {
        $generator = new RandomHashGenerator('APP_KEY');

        $this->assertEquals(64, strlen($generator->generate()));
    }

    /** @test */
    public function it_generates_a_unique_tokens(): void
    {
        $generator = new RandomHashGenerator('APP_KEY');

        $token1 = $generator->generate();
        $token2 = $generator->generate();
        $token3 = $generator->generate();
        $token4 = $generator->generate();
        $token5 = $generator->generate();

        $this->assertEquals(5, collect([$token1, $token2, $token3, $token4, $token5])->unique()->count());
    }
}

