<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Nevadskiy\Tokens\Tests\Support\Models\User;
use Nevadskiy\Tokens\Tests\TestCase;

class BasicUsageTest extends TestCase
{
    /** @test */
    public function tokens_can_be_generated_for_models(): void
    {
        $user = factory(User::class)->create();

        // Resolving the token manager
        $manager = $this->tokenManager();

        // Defining a new token (can be specified in the config/tokens.php)
        $manager->define('password.reset');

        // Generating token for the given user
        $token = $manager->generateFor($user, 'password.reset');

        $this->assertTrue($token->tokenable->is($user));

        $this->assertDatabaseHas('tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => get_class($user),
            'name' => 'password.reset',
        ]);
    }

    /** @test */
    public function tokens_can_be_used_by_name_and_token(): void
    {
        $now = $this->freezeTime();

        $user = factory(User::class)->create(['password' => 'FORGOTTEN_PASSWORD']);

        // Creating the token for user who forgot their password
        $token = $this->tokenFactory()->withName('password.reset')->for($user)->create('SUPER_SECRET_TOKEN');

        // Resolving the token manager
        $manager = $this->tokenManager();

        // Defining a new token (can be specified in the config/tokens.php)
        $manager->define('password.reset');

        // Using the token of the specified name
        $manager->use('SUPER_SECRET_TOKEN', 'password.reset', function (User $user) {
            $user->update(['password' => 'RESET_PASSWORD']);
        });

        $this->assertEquals('RESET_PASSWORD', $user->fresh()->password);
        $this->assertEquals($now, $token->fresh()->used_at);
    }

    /** @test */
    public function tokens_can_be_used_for_known_models(): void
    {
        $now = $this->freezeTime();

        $user = factory(User::class)->create(['password' => 'FORGOTTEN_PASSWORD']);

        $token = $this->tokenFactory()->for($user)->withName('password.reset')->create('SUPER_SECRET_TOKEN');

        // Resolving the token manager
        $manager = $this->tokenManager();

        // Defining a new token (can be specified in the config/tokens.php)
        $manager->define('password.reset');

        // Using the token of the specified name for the given model
        $manager->useFor('SUPER_SECRET_TOKEN', 'password.reset', $user, function () use ($user) {
            $user->update(['password' => 'RESET_HASHED_PASSWORD']);
        });

        $this->assertEquals('RESET_HASHED_PASSWORD', $user->fresh()->password);
        $this->assertEquals($now, $token->fresh()->used_at);
    }

    /** @test */
    public function token_types_can_be_defined_through_configuration(): void
    {
        config(['tokens.define' => [
            'password.reset' => [
                'ttl' => 10
            ]
        ]]);

        $tokens = $this->tokenManager()->getDefined();

        $this->assertCount(1, $tokens);
        $this->assertEquals(['ttl' => 10], $tokens['password.reset']);
    }

    /** @test */
    public function token_types_can_be_defined_through_configuration_without_options(): void
    {
        config(['tokens.define' => [
            'password.reset',
            'verification',
        ]]);

        $tokens = $this->tokenManager()->getDefined();

        $this->assertCount(2, $tokens);
        $this->assertArrayHasKey('password.reset', $tokens);
        $this->assertArrayHasKey('verification', $tokens);
    }
}
