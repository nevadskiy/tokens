# 📦 Easy one-time tokens management for Laravel
This package allows you to generate and use one-time tokens for different models.

## About
The package provides a similar to the default password_resets approach but is more unique and can be used for any type of tokens using a single database table, e.g. reset passwords, 2FA authentication, email verification, confirmation codes, magic auth links and others. 
 
## Example
```
class ResetPasswordController
{
    // Generating a token...
    public function sendLink(Request $request)
    {
        $user = User::whereEmail($request->email)->firstOrFail();
        
        $token = TokenManager::generateFor($user, 'password.reset');
        
        $user->notify(new ResetPasswordNotification($token));

        return back()->with('success', 'We have e-mailed your password reset link!')
    }

    // Using a token...
    public function resetPassword(Request $request)
    {
        $user = User::whereEmail($request->email)->firstOrFail();

        TokenManager::useFor($request->token, 'password.reset', $user, function (User $user) use ($request) {
            $uset->update(['password' => Hash::make($request->password)]);
        });

        return redirect()->route('home')->with('success', 'Your password has been reset!');
    }
}
```


## Idea
By default Laravel provides a `password_resets` table, which can be used only for password tokens.
There was a plan to replace that approach with Signed URLs (check the [PR](https://github.com/laravel/framework/pull/23706)), but it was closed because of security reasons.
Using this package, you can easily rewrite the default reset password functionality and add more token-based features.


## Features
- Single database table for all tokens
- Generating unique tokens for different entities
- Different token generators for long hash tokens or short codes
- Console command for removing dead tokens
- Throttling tokens generation to prevent spamming from SMS or SMTP services
- Throttling tokens usage to prevent brute forcing


## Requirements
- PHP >= 7.2
- Laravel >= 5.8


## Installation
TODO: publish to composer

If you don't use auto-discovering, add the package's service provider to your `config/app.php` file:
```
'providers' => [
    /*
     * Package Service Providers...
     */
    Nevadskiy\Tokens\TokenServiceProvider::class,
]
```

Publish the package's configuration file:
```
php artisan vendor:publish --provider="Nevadskiy\Tokens\TokenServiceProvider"
```

Run the migrations:
```
php artisan migrate
```


## Usage
First, you should define a new token type with needed options in the `define` array in the `config/tokens.php` file like this:
```
<?php

return [
    'define' => [
        'verification' => [
            'ttl' => 60,
            'previous' => 'remove',
        ],
    ],
];
```

#### Generating tokens
Then you can use it for generation new tokens:
```
use Nevadskiy\Tokens\Facades\TokenManager;
...
$tokenEntity = TokenManager::generateFor($yourModel, 'verification');
```
`$tokenEntity` is the instance of `TokenEntity` model which can be automatically converted to string:
```
// Using string cast
(string) $tokenEntity // 'YOUR_SECRET_TOKEN'

// Using toString() method
$tokenEntity->toString() // 'YOUR_SECRET_TOKEN'
```

#### Using tokens
Then verify generated tokens, which users can received through email or any other preferred channels, provide the token, its type and callback, which automatically retrieves a tokenable model, which token was generated for:
```
use Nevadskiy\Tokens\Facades\TokenManager;
...
TokenManager::use($request->token, 'verification', function ($tokenableModel) {
    // Token usage logic...
    $tokenableModel->update(['verified_at' => now()]);
});
```

Alternatively, if you have resolved the concrete model, for which token was generated, you can use `useFor` method like this:
```
use Nevadskiy\Tokens\Facades\TokenManager;
...
// Concrete model
$user = User::whereEmail($request->email)->firstOrFail();

// Using a token
TokenManager::useFor($request->token, 'verification', $user, function (User $user) {
    // Token usage logic...
    $tokenableModel->update(['verified_at' => now()]);
});
``` 

#### Handling exceptions
Obviously, there are a lot of cases where a token cannot be found, expired, already used or just too usage many usage attempts.
TokenManager throws corresponding exceptions for each case, so you need to catch the to show needed messages to your users like this:
```
use Nevadskiy\Tokens\Exceptions\LockoutException;
use Nevadskiy\Tokens\Exceptions\TokenException;
...
try {
    TokenManager::use($request->token, 'verification', function (User $user) {
        $tokenableModel->update(['verified_at' => now()]);
    });
    return back()->with('success', 'You have been successfully verificated.');
} catch (LockoutException $e) {
    return back()->with('error', 'Too many attempts. Try again later.');
} catch (TokenException $e) {
    return back()->with('error', 'Your verification token is broken or already expired.');
}
```

All possible exceptions extends basic `Nevadskiy\Tokens\Exceptions\TokenException`
and you can find them in the `src/Exceptions` directory.

In most cases you can just catch `LockoutException` and `TokenException` exceptions like at the example above.

#### Clear dead tokens
The package provides simple console command which removes expired, used and soft deleted tokens from the database.
`php artisan tokens:clear` 

The command can be scheduled in the `app/Console/Kernel.php` file like this:
```
/**
 * Define the application's command schedule.
 *
 * @param Schedule $schedule
 */
protected function schedule(Schedule $schedule): void
{
     $schedule->command('tokens:clear')->weekly();
}
```

#### Throttling
By default, TokenManager applies throttling to both `generate` and `use` methods. You can disable it using corresponding token options. 
Generation throttling allows you to prevent spamming from MAIL or SMS sender service, when users request token a lot of times in a short period of time.
And usage throttling allows to prevent brute force attack.

When limits are exceeded, `LockoutException` will be thrown.


## Options
| Name | Default | Description |
| --- | --- | --- |
| `ttl` | `43200` | Number of minutes how long token is alive. |
| `previous` | `remove` | Token generation strategy, when the same token already exists in the database. Can be one of `remove`, `reuse` or `keep`. |
| `generation_throttling` | `true` | Determine whether the token should use throttling for the generation process. |
| `generation_attempts` | `3` | How many attempts per user are available for generating the same token type. |
| `generation_attempts_interval` | `10` | Number of minutes how many generation attempts can be processed within. |
| `usage_throttling` | `true` | Determine whether the token should use throttling for the usage process. |
| `usage_attempts` | `5` | How many attempts per user are available for using the same token type. |
| `usage_attempts_interval` | `10` | Number of minutes how many usage attempts can be processed within. |
| `generator` | `RandomHashGenerator` | A generator class for generation token strings. |

##### Generators
You can specify one of available generator class (check the `src/Generator` directory) 
or just your custom generator which implement the `Nevadskiy\Tokens\Generator\Generator` interface. 

##### Token class
Instead of passing a defined token type as string, you can pass token as object like this:
```
TokenManager::generateFor($user, new \App\Tokens\VerificationToken);
```

Your token object must be an instance of the `Nevadskiy\Tokens\Tokens\Token` interface.
Also, if you want to use throttling, implement `GenerationLimit` / `UsageLimit` interfaces.


## 🚧 Testing
```
vendor/bin/phpunit
```

Or using docker:
```
# Install (only first time)
make install
```

```
# Run tests
make test
```

## 🔓 Security
If you discover any security related issues, please [e-mail me](mailto:nevadskiy@gmail.com) instead of using the issue tracker.


## 📜 License
The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
