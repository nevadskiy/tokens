<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Nevadskiy\Tokens\Facades\TokenManager;
use Nevadskiy\Tokens\Tests\Support\Models\User;
use Nevadskiy\Tokens\Tests\TestCase;

class TokenManagerFacadeTest extends TestCase
{
    /** @test */
    public function tokens_can_be_generated_with_token_manager_facade(): void
    {
        $user = factory(User::class)->create();

        // Defining a new token (can be specified in the config/tokens.php)
        TokenManager::define('password.reset');

        // Generating token for the given user
        $token = TokenManager::generateFor($user, 'password.reset');

        $this->assertTrue($token->tokenable->is($user));

        $this->assertDatabaseHas('tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => get_class($user),
            'name' => 'password.reset',
        ]);
    }
}
