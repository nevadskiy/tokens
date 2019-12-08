<?php

namespace Nevadskiy\Tokens\Tests\Feature\Console;

use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;

class TokenGeneratorTest extends TestCase
{
    /** @test */
    public function dead_tokens_can_be_removed_from_database(): void
    {
        $this->tokenFactory()->expired(now()->subMinute())->create();

        $activeToken = $this->tokenFactory()->expired(now()->addYear())->create();

        $this->tokenFactory()->used(now()->subYear())->create();

        $this->tokenFactory()->create()->delete();

        $this->artisan('tokens:clear');

        $tokens = TokenEntity::query()->withoutGlobalScopes()->get();

        $this->assertCount(1, $tokens);
        $this->assertTrue($tokens->contains($activeToken));
    }
}
