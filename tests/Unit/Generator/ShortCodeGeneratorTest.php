<?php

namespace Nevadskiy\Tokens\Tests\Unit\Generator;

use Nevadskiy\Tokens\Generator\ShortCodeGenerator;
use Nevadskiy\Tokens\Tests\TestCase;

/**
 * @see ShortCodeGenerator
 */
class ShortCodeGeneratorTest extends TestCase
{
    /** @test */
    public function it_generates_token_of_the_given_length(): void
    {
        $generator = new ShortCodeGenerator(5);

        $this->assertEquals(5, strlen($generator->generate()));
    }

    /** @test */
    public function it_generates_token_from_the_given_characters_pool_given_length(): void
    {
        $generator = new ShortCodeGenerator(8, 'A');

        $this->assertEquals('AAAAAAAA', $generator->generate());
    }
}

