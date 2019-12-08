<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Exception;
use Nevadskiy\Tokens\Exceptions\TokenAccessException;
use Nevadskiy\Tokens\Exceptions\TokenAlreadyUsedException;
use Nevadskiy\Tokens\Exceptions\TokenInvalidException;
use Nevadskiy\Tokens\Exceptions\TokenNotFoundException;
use Nevadskiy\Tokens\Tests\Support\Models\User;
use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenEntity;

class TokenUsageTest extends TestCase
{
    /** @test */
    public function generated_tokens_can_be_used_with_provided_callback(): void
    {
        $now = $this->freezeTime();

        $user = factory(User::class)->create(['password' => 'FORGOTTEN_PASSWORD']);

        $token = factory(TokenEntity::class)->make([
            'token' => 'SUPER_SECRET_TOKEN',
            'name' => 'reset.password',
        ]);
        $token->fillTokenable($user);
        $token->save();

        $manager = $this->tokenManager();

        $manager->define('reset.password');

        $manager->use('SUPER_SECRET_TOKEN', 'reset.password', function (User $user) {
            $user->update(['password' => 'RESET_HASHED_PASSWORD']);
        });

        $this->assertEquals('RESET_HASHED_PASSWORD', $user->fresh()->password);
        $this->assertEquals($now, $token->fresh()->used_at);
    }

    /** @test */
    public function not_found_tokens_throw_an_exception(): void
    {
        $user = factory(User::class)->create(['password' => 'FORGOTTEN_PASSWORD']);

        $manager = $this->tokenManager();

        $manager->define('password.reset');

        try {
            $manager->use('SUPER_SECRET_TOKEN', 'password.reset', function (User $user) {
                $user->update(['password' => 'RESET_HASHED_PASSWORD']);
            });
            $this->fail('Not found token was used.');
        } catch (TokenNotFoundException $e) {
            $this->assertEquals('FORGOTTEN_PASSWORD', $user->fresh()->password);
        }
    }

    /** @test */
    public function already_used_tokens_throw_an_exception(): void
    {
        $user = factory(User::class)->create(['password' => 'FORGOTTEN_PASSWORD']);

        $this->tokenFactory()->withName('password.reset')->for($user)->used(now())->create('ALREADY_USED_TOKEN');

        $manager = $this->tokenManager();

        $manager->define('password.reset');

        try {
            $manager->use('ALREADY_USED_TOKEN', 'password.reset', function (User $user) {
                $user->update(['password' => 'RESET_HASHED_PASSWORD']);
            });
            $this->fail('Token was used second time.');
        } catch (TokenAlreadyUsedException $e) {
            $this->assertEquals('FORGOTTEN_PASSWORD', $user->fresh()->password);
        }
    }

    /** @test */
    public function token_will_not_be_marked_as_used_if_callback_throws_an_exception(): void
    {
        $token = $this->tokenFactory()->withName('password.reset')->create('SECRET_TOKEN');

        $manager = $this->tokenManager();

        $manager->define('password.reset');

        try {
            $manager->use('SECRET_TOKEN', 'password.reset', function () {
                throw new Exception('Test exception');
            });
            $this->fail('Token was used when exception was thrown.');
        } catch (Exception $e) {
            $this->assertFalse($token->fresh()->isUsed());
        }
    }

    /** @test */
    public function exception_will_be_thrown_when_trying_to_use_token_of_another_model(): void
    {
        $user = $this->createTokenableEntity();
        $anotherUser = $this->createTokenableEntity();

        $token = $this->tokenFactory()->withName('password.reset')->for($user)->create('SECRET_TOKEN');

        $manager = $this->tokenManager();

        $manager->define('password.reset');

        try {
            $manager->useFor('SECRET_TOKEN', 'password.reset', $anotherUser, function () {
                $this->fail('Token was used for another user.');
            });
        } catch (TokenAccessException $e) {
            $this->assertTrue($user->is($e->getOwner()));
            $this->assertFalse($token->fresh()->isUsed());
        }
    }

    /** @test */
    public function exception_is_thrown_when_token_is_null(): void
    {
        $manager = $this->tokenManager();

        $manager->define('password.reset');

        $this->expectException(TokenInvalidException::class);

        $manager->use(null, 'password.reset', function () {
            $this->fail('Token was used for another user.');
        });
    }

    /** @test */
    public function token_will_not_be_marked_as_used_if_callback_returns_false(): void
    {
        $user = factory(User::class)->create(['password' => 'FORGOTTEN_PASSWORD']);

        $token = $this->tokenFactory()->withName('reset.password')->for($user)->create('SUPER_SECRET_TOKEN');

        $manager = $this->tokenManager();

        $manager->define('reset.password');

        $manager->use('SUPER_SECRET_TOKEN', 'reset.password', function (User $user) {
            $user->update(['password' => 'RESET_HASHED_PASSWORD']);
            return false;
        });

        $this->assertEquals('RESET_HASHED_PASSWORD', $user->fresh()->password);
        $this->assertNull($token->fresh()->used_at);
    }
}
