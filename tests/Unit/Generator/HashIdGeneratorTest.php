<?php

namespace Nevadskiy\Tokens\Tests\Unit\Generator;

use Nevadskiy\Tokens\Generator\HashIdGenerator;
use Nevadskiy\Tokens\Tests\TestCase;

/**
 * @see HashIdGenerator
 */
class HashIdGeneratorTest extends TestCase
{
    /** @test */
    public function it_generates_tokens_as_hash_ids(): void
    {
        $user = $this->createTokenableEntity();

        $generator = new HashIdGenerator($user->getKey(), get_class($user), 5);

        $token1 = $generator->generate();
        $token2 = $generator->generate();

        $this->assertEquals($token1, $token2);
        $this->assertEquals(5, strlen($token1));
    }
}

