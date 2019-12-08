<?php

namespace Nevadskiy\Tokens\Tests\Feature;

use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Nevadskiy\Tokens\Exceptions\LockoutException;
use Nevadskiy\Tokens\Exceptions\TokenNotFoundException;
use Nevadskiy\Tokens\Tests\Support\Models\User;
use Nevadskiy\Tokens\Tests\TestCase;
use Nevadskiy\Tokens\TokenManager;
use UnexpectedValueException;

class ThrottlingUsageTest extends TestCase
{
    /** @test */
    public function wrong_token_usage_attempts_are_limited_for_client(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'usage_attempts' => 3,
            'usage_attempts_interval' => 10,
        ]);

        try {
            $this->wrongTokenUsageAttempts($manager, 4, 'reset.password', 'WRONG_TOKEN');
            $this->fail('Token was attempt too many times without exception.');
        } catch (LockoutException $e) {
            $this->assertEquals(now()->addMinutes(10), $e->getUnlockTime());
        }
    }

    /** @test */
    public function wrong_token_attempt_are_reset_after_success_attempt(): void
    {
        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'usage_attempts' => 3,
            'usage_attempts_interval' => 10,
        ]);

        $token = $this->tokenFactory()->withName('reset.password')->create('AABBCC');

        $this->wrongTokenUsageAttempts($manager, 2, 'reset.password', 'WRONG_TOKEN');

        $manager->use('AABBCC', 'reset.password', function () {});

        $this->wrongTokenUsageAttempts($manager, 3, 'reset.password', 'WRONG_TOKEN');

        $this->assertTrue($token->fresh()->isUsed());
    }

    /** @test */
    public function wrong_token_attempt_are_reset_after_timeout(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'usage_attempts' => 3,
            'usage_attempts_interval' => 10,
        ]);

        $this->tokenFactory()->withName('reset.password')->create('AABBCC');

        $this->wrongTokenUsageAttempts($manager, 3, 'reset.password', 'WRONG_TOKEN');

        $this->freezeTime(now()->addMinutes(10)->addSeconds(1));

        $this->wrongTokenUsageAttempts($manager, 3, 'reset.password', 'WRONG_TOKEN');

        $this->expectException(LockoutException::class);

        $this->wrongTokenUsageAttempts($manager, 1, 'reset.password', 'WRONG_TOKEN');
    }

    /** @test */
    public function token_usage_attempts_is_5_by_default(): void
    {
        $manager = $this->tokenManager();

        $manager->define('reset.password');

        $this->wrongTokenUsageAttempts($manager, 5, 'reset.password', 'WRONG_TOKEN');

        $this->expectException(LockoutException::class);

        $this->wrongTokenUsageAttempts($manager, 1, 'reset.password', 'WRONG_TOKEN');
    }

    /** @test */
    public function token_usage_attempts_timeout_is_10_minutes_by_default(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('reset.password');

        try {
            $this->wrongTokenUsageAttempts($manager, 6, 'reset.password', 'WRONG_TOKEN');
        } catch (LockoutException $e) {
            $this->assertEquals(10, now()->diffInMinutes($e->getUnlockTime()));
        }
    }

    /** @test */
    public function token_usage_throttling_can_be_disabled(): void
    {
        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'usage_throttling' => false,
            'usage_attempts' => 1,
        ]);

        $user = $this->createTokenableEntity();

        $token = $this->tokenFactory()->withName('reset.password')->for($user)->create('TEST_TOKEN');

        $this->wrongTokenUsageAttempts($manager, 1, 'reset.password', 'WRONG_TOKEN');

        $manager->use('TEST_TOKEN', 'reset.password', function (User $user) {
            $user->delete();
        });

        $this->assertNull($user->fresh());
        $this->assertTrue($token->fresh()->isUsed());
    }

    /** @test */
    public function token_usage_throttling_interval_can_be_specified_as_date_interval(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'usage_throttling' => true,
            'usage_attempts' => 1,
            'usage_attempts_interval' => CarbonInterval::month(),
        ]);

        $this->wrongTokenUsageAttempts($manager, 1, 'reset.password', 'WRONG_TOKEN');

        try {
            $this->wrongTokenUsageAttempts($manager, 1, 'reset.password', 'WRONG_TOKEN');
        } catch (LockoutException $e) {
            $this->assertEquals(now()->addMonth(), $e->getUnlockTime());
        }
    }

    /** @test */
    public function exception_will_be_thrown_for_unknown_interval_format(): void
    {
        $this->freezeTime();

        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'usage_throttling' => true,
            'usage_attempts' => 1,
            'usage_attempts_interval' => ['INVALID_INTERVAL'],
        ]);

        $this->expectException(UnexpectedValueException::class);

        $this->wrongTokenUsageAttempts($manager, 1, 'reset.password', 'WRONG_TOKEN');
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function token_usage_throttling_counts_attempts_by_token_type(): void
    {
        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'usage_attempts' => 1,
        ]);

        $manager->define('verification', [
            'usage_attempts' => 1,
        ]);

        $this->wrongTokenUsageAttempts($manager, 1, 'reset.password', 'WRONG_TOKEN');
        $this->wrongTokenUsageAttempts($manager, 1, 'verification', 'WRONG_TOKEN');
    }

    /**
     * @test
     */
    public function token_usage_throttling_counts_attempts_by_ip_address(): void
    {
        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'usage_attempts' => 1,
        ]);

        $this->wrongTokenUsageAttempts($manager, 1, 'reset.password', 'WRONG_TOKEN');

        // Change IP address
        $request = $this->partialMock(Request::class);
        $request->shouldReceive('ip')->andReturn('another-ip-address');
        $this->app->instance('request', $request);

        $this->wrongTokenUsageAttempts($manager, 1, 'reset.password', 'WRONG_TOKEN');
    }

    /** @test */
    public function tokens_usage_attempts_must_be_at_least_1(): void
    {
        $manager = $this->tokenManager();

        $manager->define('reset.password', [
            'usage_attempts' => 0,
        ]);

        $this->expectException(UnexpectedValueException::class);

        $this->wrongTokenUsageAttempts($manager, 1, 'reset.password', 'WRONG_TOKEN');
    }

    /**
     * @param TokenManager $manager
     * @param int $attempts
     * @param string $type
     * @param string $token
     * @throws LockoutException
     * @throws \Nevadskiy\Tokens\Exceptions\TokenException
     */
    protected function wrongTokenUsageAttempts(TokenManager $manager, int $attempts, string $type, string $token = 'WRONG')
    {
        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $manager->use($token, $type, function () {
                    $this->fail('Token was used when should not.');
                });
            } catch (TokenNotFoundException $e) {
            }
        }
    }
}
